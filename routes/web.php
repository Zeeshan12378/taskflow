<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('/features', 'pages.features')->name('features');
Route::view('/about', 'pages.about')->name('about');

Route::livewire('/login', 'auth.login')
    ->middleware('guest')
    ->name('login');

Route::livewire('/register', 'auth.register')
    ->middleware('guest')
    ->name('register');

Route::livewire('/dashboard', 'dashboard.index')
    ->middleware('auth')
    ->name('dashboard');

Route::livewire('/projects', 'projects.index')
    ->middleware('auth')
    ->name('projects.index');

Route::livewire('/teams', 'teams.index')
    ->middleware('auth')
    ->name('teams.index');

Route::livewire('/teams/{team}', 'teams.show')
    ->middleware('auth')
    ->name('teams.show');

Route::livewire('/projects/create', 'projects.form')
    ->middleware('auth')
    ->name('projects.create');

Route::livewire('/projects/{project}/board', 'projects.board')
    ->middleware('auth')
    ->name('projects.board');

Route::livewire('/projects/{project}/list', 'projects.list')
    ->middleware('auth')
    ->name('projects.list');

Route::livewire('/projects/{project}/sprints', 'projects.sprints')
    ->middleware('auth')
    ->name('projects.sprints');

Route::livewire('/projects/{project}/calendar', 'projects.calendar')
    ->middleware('auth')
    ->name('projects.calendar');

Route::livewire('/projects/{project}/reports', 'projects.reports')
    ->middleware('auth')
    ->name('projects.reports');

Route::livewire('/projects/{project}/tasks/create', 'tasks.form')
    ->middleware('auth')
    ->name('projects.tasks.create');

Route::livewire('/projects/{project}/tasks/{task}/edit', 'tasks.form')
    ->middleware('auth')
    ->name('projects.tasks.edit');

Route::livewire('/projects/{project}/edit', 'projects.form')
    ->middleware('auth')
    ->name('projects.edit');

Route::livewire('/projects/{project}', 'projects.show')
    ->middleware('auth')
    ->name('projects.show');

Route::livewire('/tasks', 'tasks.index')
    ->middleware('auth')
    ->name('tasks.index');

Route::livewire('/my-tasks', 'tasks.index')
    ->middleware('auth')
    ->name('tasks.mine');

Route::livewire('/notifications', 'notifications.index')
    ->middleware('auth')
    ->name('notifications.index');

Route::livewire('/profile', 'profile.index')
    ->middleware('auth')
    ->name('profile');

Route::livewire('/settings', 'settings.index')
    ->middleware('auth')
    ->name('settings');
