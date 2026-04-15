<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('comments.course.{id}', function ($user, $id) {
    return true; 
});

Broadcast::channel('comments.job_description.{id}', function ($user, $id) {
    return true; 
});