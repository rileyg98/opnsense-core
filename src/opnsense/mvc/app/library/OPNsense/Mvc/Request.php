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

class Request
{
    public array $queryParameters;
    private string $rawBody = '';

    public function __construct($queryParameters)
    {
        $this->queryParameters = $queryParameters;
    }

    public function getHeader(string $header): string
    {
        $name = strtoupper(strtr($header, "-", "_"));
        if (isset($_SERVER[$name])) {
            return $_SERVER[$name];
        } elseif (isset($_SERVER["HTTP_{$name}"])) {
            return $_SERVER["HTTP_{$name}"];
        }
        return '';
    }

    public function getMethod()
    {
        // XXX: X-HTTP-Method-Override ?
        return $_SERVER["REQUEST_METHOD"];
    }

    public function isPost()
    {
        return $this->getMethod() == 'POST';
    }

    public function isGet()
    {
        return $this->getMethod() == 'GET';
    }

    public function isPut()
    {
        return $this->getMethod() == 'PUT';
    }

    public function isDelete()
    {
        return $this->getMethod() == 'DELETE';
    }

    public function isHead()
    {
        return $this->getMethod() == 'HEAD';
    }

    public function getRawBody()
    {
        if (empty($this->rawBody)) {
            $this->rawBody = file_get_contents("php://input");
        }
        return $this->rawBody;
    }

}