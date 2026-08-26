<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class VerifyPaystackSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.paystack.secret_key');
        $signature = $request->header('x-paystack-signature');
        $expected = $secret ? hash_hmac('sha512', $request->getContent(), $secret) : null;

        if (! $secret || ! $signature || ! $expected || ! hash_equals($expected, $signature)) {
            throw new AccessDeniedHttpException('Invalid Paystack webhook signature.');
        }

        return $next($request);
    }
}
