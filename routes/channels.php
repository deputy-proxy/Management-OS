<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
/*
Broadcast::channel('comments.course.{id}', function ($user, $id) {
    // Return true if user can view the course
    return true; 
});
*/