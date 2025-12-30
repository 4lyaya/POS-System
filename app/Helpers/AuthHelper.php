<?php

namespace App\Helpers;

use App\Models\User;

class AuthHelper
{
    public static function getCurrentUser()
    {
        return auth()->user();
    }
    
    public static function checkPermission($permission)
    {
        $user = auth()->user();
        return $user && $user->hasPermission($permission);
    }
    
    public static function isAdmin()
    {
        $user = auth()->user();
        return $user && $user->isAdmin();
    }
    
    public static function isOperator()
    {
        $user = auth()->user();
        return $user && $user->isOperator();
    }
    
    public static function isCashier()
    {
        $user = auth()->user();
        return $user && $user->isCashier();
    }
    
    public static function getRoleName()
    {
        $user = auth()->user();
        return $user ? $user->role->name : 'Guest';
    }
    
    public static function getUserPhoto()
    {
        $user = auth()->user();
        return $user ? $user->photo_url : asset('images/default-avatar.png');
    }
}
