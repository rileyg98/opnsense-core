<?php

/*
 * Copyright (C) 2024 Deciso B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace OPNsense\Mvc;

use ReflectionClass;
use OPNsense\Mvc\Exceptions\ClassNotFound;
use OPNsense\Mvc\Exceptions\MethodNotFound;
use OPNsense\Mvc\Exceptions\ParameterMismatch;


class Dispatcher
{
    private string $namespace;
    private string $controller;
    private string $action;
    private array $parameters;
    private \ReflectionClass $controllerClass;
    private \ReflectionMethod $actionMethod;
    private $returnedValue;


    public function __construct($namespace, $controller, $action, $parameters)
    {
        $this->namespace = $namespace;
        $this->controller = $controller;
        $this->action = $action;
        $this->parameters = $parameters;
    }

    /**
     * Resolve controller class and method to call
     * @throws ClassNotFound when controller class can not be found
     * @throws MethodNotFound when controller method can not be found
     * @throws ParameterMismatch when expected required parameters do not match offered ones
     */
    protected function resolve(): void
    {
        if (isset($this->actionMethod)) {
            // already resolved
            return;
        }
        $clsname = $this->namespace . "\\" . $this->controller;
        $this->controllerClass = new \ReflectionClass($clsname);
        if (!$this->controllerClass->isInstantiable()) {
            throw new ClassNotFound(sprintf("%s not found", $clsname));
        } elseif (!$this->controllerClass->hasMethod($this->action)) {
            throw new MethodNotFound(sprintf("%s -> %s not found", $clsname, $this->action));
        }
        $this->actionMethod = $this->controllerClass->getMethod($this->action);
        $pcount = 0;
        foreach ($this->actionMethod->getParameters() as $param) {
            if ($param->isOptional()) {
                break;
            }
            $pcount++;
        }
        if ($pcount > count($this->parameters)) {
            unset($this->actionMethod);
            throw new ParameterMismatch(sprintf(
                "%s -> %s parameter mismatch (expected %d, got %d)",
                $clsname,
                $this->action,
                $pcount,
                count($this->parameters)
            ));
        }
    }

    /**
     * test if controller action method is callable with the parameters provided
     * @return bool
     */
    public function CanExecute() : bool
    {
        try {
            $this->resolve();
            return true;
        }catch(\Exception $ex){
            return false;
        }
    }

    /**
     * Dispatch (execute) controller action method
     * @throws ClassNotFound when controller class can not be found
     * @throws MethodNotFound when controller method can not be found
     * @throws ParameterMismatch when expected required parameters do not match offered ones
     */
    public function Dispatch($request, $response, $session) : bool
    {
        $this->resolve();

        $controller = $this->controllerClass->newInstance();
        $controller->session = $session;
        $controller->request = $request;
        $controller->response = $response;

        $controller->initialize();

        if ($controller->beforeExecuteRoute($this) === false) {
            return false;
        }
        $this->returnedValue = $this->actionMethod->invoke($controller, ... $this->parameters);
        return $controller->afterExecuteRoute($this);
    }

    /**
     * @return action response
     */
    public function getReturnedValue()
    {
        return $this->returnedValue;
    }
}