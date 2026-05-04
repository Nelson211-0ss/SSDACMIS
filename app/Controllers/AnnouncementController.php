<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;

class AnnouncementController extends Controller
{
    public function index(): string
    {
        $items = Database::query(
            "SELECT a.*, u.name AS author_name FROM announcements a
             LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.created_at DESC"
        )->fetchAll();
        return $this->view('announcements/index', compact('items'));
    }

    public function store(): string
    {
        $this->validateCsrf();
        $title = trim((string) $this->input('title'));
        $body  = trim((string) $this->input('body'));
        if ($title === '' || $body === '') {
            Flash::set('danger', 'Title and body are required.');
            $this->redirect('/announcements'); return '';
        }
        Database::query(
            "INSERT INTO announcements (user_id, title, body) VALUES (?, ?, ?)",
            [Auth::user()['id'], $title, $body]
        );
        Flash::set('success', 'Announcement posted.');
        $this->redirect('/announcements'); return '';
    }
}
