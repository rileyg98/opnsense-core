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

use OPNsense\Core\AppConfig;

class Router
{
    private string $prefix;
    private string $namespace_suffix;

    /**
     * construct new router
     */
    public function __construct($prefix, $namespace_suffix = 'Api')
    {
        $this->prefix = $prefix;
        $this->namespace_suffix = $namespace_suffix;
    }

    /**
     * probe for namespace in specified AppConfig controllersDir
     * @param string $namespace base namespace to search for (without vendor)
     * @param string $controller controller class name
     * @return string|null namespace with vendor when found
     */
    private function resolveNamespace(string $namespace, string $controller)
    {
        $appconfig = new AppConfig();
        foreach ((array)$appconfig->application->controllersDir as $controllersDir) {
            // sort OPNsense namespace on top
            $dirs = glob($controllersDir. "/*", GLOB_ONLYDIR);
            usort($dirs, function($a, $b){
                if (basename($b) == 'OPNsense') {
                    return 1;
                } else {
                    return strcmp(strtolower($a), strtolower($b));
                }
            }) ;
            foreach ($dirs as $dirname) {
                $basename = basename($dirname);
                $new_namespace = "{$basename}\\{$namespace}";
                if (!empty($this->namespace_suffix)) {
                    $expected_filename = "{$dirname}/{$namespace}/{$this->namespace_suffix}/{$controller}.php";
                    $new_namespace .= "\\" . $this->namespace_suffix;
                } else {
                    $expected_filename = "{$dirname}/{$namespace}/{$controller}.php";
                }
                if (is_file($expected_filename)) {
                    return  $new_namespace;
                }
            }
        }
        return null;
    }

    /**
     * Route a request
     * @param string $uri
     * @return Response to be rendered
     */
    public function RouteRequest(string $uri) : Response
    {
        $urlParts = parse_url($uri);
        $path  = $urlParts['path'];

        if (!str_starts_with($path, $this->prefix)) {
            throw new \Exception("Invalid route path: " . $uri);
        }

        // extract target (base)namespace, controller and action
        $targetAndParameters = $this->parsePath(substr($path, strlen($this->prefix)));

        $controller = $targetAndParameters['controller'];
        $namespace = $this->resolveNamespace($targetAndParameters['namespace'], $controller);
        $action = $targetAndParameters['action'];
        $parameters = $targetAndParameters['parameters'];

        if ($action === null || $controller === null || $namespace === null) {
            // XXX: default route path
            throw new \Exception("Invalid route path, no action, controller, and / or namespace: " . $uri);
        }

        if (!empty($urlParts['query'])) {
            parse_str($urlParts['query'], $queryParams);
        } else {
            $queryParams = [];
        }

        $dispatcher = new Dispatcher($namespace, $controller, $action, $parameters);
        $dispatcher->CanExecute();
        // if (!$dispatcher->CanExecute()) {
        //     // XXX: error handling
        //     throw new \Exception("Cannot dispatch request for some reason" . $uri);
        // }

        return $this->PerformRequest($dispatcher, $queryParams);
    }

    /**
     * @param Dispatcher $dispatcher request dispatcher
     * @param array $queryParams request parameters
     * @return Response object
     */
    private function PerformRequest(Dispatcher $dispatcher, array $queryParams) : Response
    {
        $session = new Session();
        $request = new Request($queryParams);
        $response = new Response();

        $dispatcher->Dispatch($request, $response, $session);
        return $response;
    }


    /**
     * @param string $path path to extract
     * @return array containing expected controller action
     */
    private function ParsePath(string $path) : array {
        $pathElements = explode("/", $path);
        $result = [
            "namespace" =>null,
            "controller" =>null,
            "action" =>null,
            "parameters" => []
        ];

        $count = 0;
        foreach($pathElements as $element){
            if($count == 0) {
                $result["namespace"] = $this->EnforceCamelCase($element);
            } else if($count == 1) {
                $result["controller"] = $this->EnforceCamelCase($element) . 'Controller';
            } else if($count == 2) {
                $result["action"] = $this->EnforceCamelCase($element)  . "Action";
            }else{
                $result["parameters"][] = $element;
            }
            $count++;
        }

        return $result;
    }

    /**
     * @param string $value value to CamelCase
     * @return string value in CamelCase, e.g. my_value => MyValue
     */
    private function EnforceCamelCase(string $value) : string {
        if (stripos($value, "_") === 0){
            return ucfirst($value);
        }

        $camelCaseResult = "";
        foreach(explode('_', $value) as $part){
            $camelCaseResult .= ucfirst($part);
        }

        return $camelCaseResult;
    }
}