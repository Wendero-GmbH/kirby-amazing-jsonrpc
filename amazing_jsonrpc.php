<?php

namespace Kirby\Registry {
  use Kirby;
  use Kirby\Registry;

  class Rpc extends Entry {
    public function set($name, callable $callback) {
      $this->kirby->rpc->add($name, $callback);
    }
  }
}

namespace JsonRpc {

  class RpcDispatcher {

    const JSON_RPC_VERSION = '2.0';
    const JSON_PARSE_ERROR = -32700;
    const INVALID_REQUEST = -32600;
    const METHOD_NOT_FOUND = -32601;
    const INVALID_PARAMS = -32602;
    const INTERNAL_ERROR = -32603;

    private $methods = [];

    /**
     * @param Kirby $kirby
     */
    public function __construct(\Kirby $kirby) {
      $this->methods = [];

      $kirby->set('route', [
        'pattern' => 'jsonrpc',
        'method' => 'POST',
        'action' => [$this, 'onMessage']
      ]);
    }

    /**
     * @param int $code
     * @param string $message
     * @return \Response
     */
    private static function error($code, $message) {

      if (!is_int($code)) throw new \Exception('code must be int');

      $args = array_slice(func_get_args(), 1);

      return new \Response([
        'error' => [
          'message' => call_user_func_array('sprintf', $args),
          'code' => $code
        ]
      ], 'json', 504);
    }

    /**
     * @return \Response
     */
    public function onMessage() {

      $payloadRaw = file_get_contents('php://input');

      $payload = json_decode($payloadRaw, true);

      if (is_null($payload) || !is_array($payload)) {
        return self::error(self::JSON_PARSE_ERROR, 'unable to parse json');
      }

      if (!array_key_exists('id', $payload)) {
        return self::error(0, 'missing id');
      }

      $id = $payload['id'];

      if (!is_int($id) && !is_null($id)) {
        return self::error(0, 'id must be either null or an integer');
      }

      $version = $payload['jsonrpc'];

      if ($version !== self::JSON_RPC_VERSION) {
        return self::error(0, 'jsonrpc must be %s, was %s', self::JSON_RPC_VERSION, $version);
      }

      $method = $payload['method'];

      if (!array_key_exists($method, $this->methods)) {
        return self::error(self::METHOD_NOT_FOUND, 'method %s is missing', $method);
      }

      $cb = $this->methods[$method];

      $info = new \ReflectionFunction($cb);

      $nargs = $info->getNumberOfRequiredParameters();

      if (!array_key_exists('params', $payload)) {
        return self::error(0, 'missing params');
      }

      $params = $payload['params'];

      if (!is_array($params) || $nargs !== count($params)) {
        return self::error(self::INVALID_PARAMS, 'Method expects %d params, but got %d', $nargs, count($params));
      }

      try {
        $r = call_user_func_array($cb, $params);

        return new \Response([
          'result' => $r,
          'jsonrpc' => self::JSON_RPC_VERSION,
          'id' => $id
        ], 'json');
      }
      catch (Exception $e) {
        return self::error(self::INTERNAL_ERROR, 'error :(');
      }

    }

    /**
     * @param string $name
     * @param callable $callback
     */
    public function add($name, callable $callback) {
      if (!is_string($name)) {
        throw new \Exception('method name must be string');
      }

      if (array_key_exists($name, $this->methods)) {
        throw new \Exception("method name $name already taken");
      }

      $this->methods[$name] = $callback;
    }
  }

  $kirby->rpc = new \JsonRpc\RpcDispatcher(kirby());  
}

