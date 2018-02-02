# JSON-RPC Plugin for Kirby CMS

Install this plugin to be able to easily define an API based on [JSON-RPC](http://www.jsonrpc.org/specification) in your own Kirby plugins.

## Usage

For example, a procedure that returns all registered users could be set up as follows:

```php
kirby()->set('rpc', [
  'method' => 'get_users',
  'action' => function () {
    return kirby()->site()->users()->toJson();
  }
]);
```

You would call it by making a `POST` request to the `/jsonrpc` endpoint with the following body:

```json
{
    "jsonrpc" : "2.0",
    "method" : "get_users",
    "params" : [],
    "id" : 1
}
```

As a second example, this is how you would define a procedure that adds two numbers:

```php
kirby()->set('rpc', [
  'method' => 'add',
  'action' => function ($a, $b) {
    return $a + $b;
  }
]);
```

```json
{
    "jsonrpc" : "2.0",
    "method" : "add",
    "params" : [3, 4],
    "id" : 234
}
```
The response returned by the server will be:
```json
{
    "jsonrpc" : "2.0",
    "result" : 7,
    "id" : 234
}
```


If you want to make a method available only to users with certain role, then you can add a whitelist of roles as follows:

```php
kirby()->set('rpc', [
  'method' => 'add',
  'roles' => ['admin'],
  'action' => function () {
    return 'Hi!';
  }
]);
```

## Installation

Just copy the plugin directory into `app/site/plugins` and make sure it is called `amazing_jsonrpc`.


