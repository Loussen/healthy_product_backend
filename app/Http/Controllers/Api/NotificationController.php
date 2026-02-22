<?php

namespace App\Http\Controllers\Api;

use App\Models\PushNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationController extends BaseController
{
    public function getPushNotifications(Request $request)
    {
        $user = $request->user();

        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        $query = PushNotification::where('customer_id', $user->id)->orderBy('id', 'desc');

        $paginatedResults = $query->paginate($perPage, ['*'], 'page', $page);

        $response = [
            'data' => $paginatedResults->items(),
            'pagination' => [
                'current_page' => $paginatedResults->currentPage(),
                'last_page' => $paginatedResults->lastPage(),
                'per_page' => $paginatedResults->perPage(),
                'total' => $paginatedResults->total(),
            ]
        ];

        return $this->sendResponse($response, 'success');
    }

    public function getPushNotification($notificationId, Request $request)
    {
        $user = $request->user();

        $getPushNotification = PushNotification::find($notificationId);

        if(!$getPushNotification || $getPushNotification->customer_id !== $user->id) {
            return $this->sendError('not_found', 'Not found notification', 404);
        }

        return $this->sendResponse($getPushNotification, 'success');
    }

    public function updateStatusPushNotification(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:push_notifications,id',
            'status' => 'required|string|in:read,unread'
        ]);

        if ($validator->fails()) {
            return $this->sendError('validation_error', $validator->errors(), 400);
        }

        $getPushNotification = PushNotification::find($request->id);

        if(!$getPushNotification || $getPushNotification->customer_id !== $user->id) {
            return $this->sendError('not_found', 'Not found notification', 404);
        }

        $getPushNotification->status = $request->status;
        $getPushNotification->save();

        return $this->sendResponse($getPushNotification, 'Notification status updated successfully.');
    }

    public function getUnreadNotificationCount(Request $request)
    {
        $user = $request->user();

        $unreadCount = PushNotification::where('customer_id', $user->id)
            ->where('status', 'unread')
            ->count();

        return $this->sendResponse([
            'count' => $unreadCount
        ], 'success');
    }
}
