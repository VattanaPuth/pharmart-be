<?php

namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

// class RoleMiddleware
// {
//     public function handle(Request $request, Closure $next, string ...$roles): mixed
//     {
//         //$user = $request->user(); // 🔥 correct way
//         $user = auth('api')->user(); 

//         if (! $user) {
//             return response()->json(['message' => 'Unauthenticated'], 401);
//         }

//         if (! in_array($user->role, $roles, true)) {
//             return response()->json(['message' => 'Forbidden'], 403);
//         }

//         return $next($request);
//     }
// }

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        $user = $request->user(); // ✅ uses current guard (admin)

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if (! in_array($user->role, $roles, true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}