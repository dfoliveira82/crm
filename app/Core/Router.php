<?php
declare(strict_types=1);
namespace App\Core;
final class Router {
    private array $routes=[];
    public function add(string $method,string $pattern,callable $handler,array $middleware=[]): void {
        $regex=preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',fn($m)=>'(?P<'.$m[1].'>[^/]+)',$pattern);
        $this->routes[]=['method'=>strtoupper($method),'regex'=>'#^'.$regex.'$#','handler'=>$handler,'middleware'=>$middleware];
    }
    public function dispatch(Request $request): Response {
        foreach($this->routes as $route) {
            if($route['method']!==$request->method() || !preg_match($route['regex'],$request->path(),$matches)) continue;
            foreach($route['middleware'] as $middleware) $middleware($request);
            $params=array_filter($matches,'is_string',ARRAY_FILTER_USE_KEY);
            return ($route['handler'])($request,$params);
        }
        return Response::html('Página não encontrada',404);
    }
}
