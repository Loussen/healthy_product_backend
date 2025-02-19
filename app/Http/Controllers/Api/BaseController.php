<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller as Controller;
use Illuminate\Http\JsonResponse;

class BaseController extends Controller
{
    /**
     * success response method.
     *
     * @return JsonResponse
     */
    public function sendResponse($result, $message, $code = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'response' => $result
        ];

        return response()->json($response, $code);
    }



    /**
     * return error response.
     *
     * @return JsonResponse
     */
    public function sendError($error, $errorMessages = [], $code = 404): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $error,
        ];

        if(!empty($errorMessages)){
            $response['data'] = $errorMessages;
        }

        return response()->json($response, $code);
    }
}
