<?php
namespace App\Controllers;

use App\Core\Controller;

class LandingController extends Controller
{
    public function index(): string
    {
        return $this->view('landing/index', [
            'title' => 'SSDACMIS — Student Management Information System',
        ]);
    }
}
