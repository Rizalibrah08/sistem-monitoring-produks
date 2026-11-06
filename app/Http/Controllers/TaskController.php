<?php

namespace App\Http\Controllers;

use App\Services\TaskList;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    /**
     * Daftar tugas dokumen staf sesuai role-nya (FR-43).
     */
    public function __invoke(Request $request, TaskList $tasks): Response
    {
        return Inertia::render('Backend/Tasks', ['tasks' => $tasks->for($request->user())]);
    }
}
