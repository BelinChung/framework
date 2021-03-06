<?php

namespace Pagon\Middleware;

use Pagon\Middleware;
use Opauth as OPAuthService;

class OPAuth extends Middleware
{
    // Some options
    protected $injectors = array(
        'login_url'          => '/login',
        'callback_url'       => '/login/callback',
        'callback_transport' => 'post'
    );

    /**
     * @param array $injectors
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function __construct(array $injectors = array())
    {
        if (!class_exists('\Opauth')) throw new \RuntimeException("OPAuth middleware need \Opauth class, plz use composer to install, or add it manually!");

        if (!isset($injectors['callback'])) throw new \InvalidArgumentException('OPAuth middleware need "callback" option');

        if (isset($injectors['strategies'])) {
            $injectors['Strategy'] = & $injectors['strategies'];
        }

        parent::__construct($injectors);

        $this->injectors['path'] = $this->injectors['login_url'] . '/';
    }

    /**
     * Call
     *
     * @return bool|void
     */
    public function call()
    {
        $options = $this->injectors;
        $app = $this->app;
        $that = $this;

        $callback = function ($req, $res, $next) use ($that, $app, $options) {
            $opauth = new OPAuthService($options, false);

            $response = unserialize(base64_decode($app->input->data('opauth')));
            $reason = null;
            $auth = false;

            if (isset($response['error'])) {
                $reason = $response['error'];
            } else {
                if (empty($response['auth']) || empty($response['timestamp']) || empty($response['signature']) || empty($response['auth']['provider']) || empty($response['auth']['uid'])) {
                    $reason = 'Missing key auth response components';
                } elseif (!$opauth->validate(sha1(print_r($response['auth'], true)), $response['timestamp'], $response['signature'], $_reason)) {
                    $reason = $_reason;
                } else {
                    $auth = $response['auth'];
                }
            }

            $req->auth = $auth;
            $req->auth_error = $reason;
            $next();
        };

        $init = function ($req, $res, $next) use ($options) {
            if (isset($options['Strategy'][$req->params['strategy']])) {
                new OPAuthService($options);
            } else {
                $next();
            }
        };

        // Register url routes
        $app->post($options['callback_url'], $callback, $options['callback']);
        $app->get($options['login_url'] . '/:strategy', $init);
        $app->all($options['login_url'] . '/:strategy/:return', $init);

        $this->next();
    }
}
