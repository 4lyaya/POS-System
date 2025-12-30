<?php

namespace App\Traits;

trait ApiResponse
{
    protected function success($data = null, $message = 'Success', $code = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function error($message = 'Error occurred', $errors = null, $code = 400)
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    protected function validationError($errors, $message = 'Validation failed')
    {
        return $this->error($message, $errors, 422);
    }

    protected function notFound($message = 'Resource not found')
    {
        return $this->error($message, null, 404);
    }

    protected function unauthorized($message = 'Unauthorized')
    {
        return $this->error($message, null, 401);
    }

    protected function forbidden($message = 'Forbidden')
    {
        return $this->error($message, null, 403);
    }

    protected function paginated($data, $message = 'Success')
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
            'links' => [
                'first' => $data->url(1),
                'last' => $data->url($data->lastPage()),
                'prev' => $data->previousPageUrl(),
                'next' => $data->nextPageUrl(),
            ],
        ]);
    }

    protected function created($data = null, $message = 'Resource created successfully')
    {
        return $this->success($data, $message, 201);
    }

    protected function updated($data = null, $message = 'Resource updated successfully')
    {
        return $this->success($data, $message);
    }

    protected function deleted($message = 'Resource deleted successfully')
    {
        return $this->success(null, $message);
    }
}
