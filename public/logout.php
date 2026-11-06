<?php
require_once __DIR__ . '/../app/bootstrap.php';
Auth::logout();
session_start();
flash('success', 'You have been logged out.');
redirect(url('public/login.php'));
