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
        $schoolId = Auth::schoolId();
        $sf = $schoolId !== null ? ' AND a.school_id = ?' : '';
        $sp = $schoolId !== null ? [$schoolId] : [];

        $items = Database::query(
            "SELECT a.*, u.name AS author_name FROM announcements a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE 1=1{$sf}
             ORDER BY a.created_at DESC",
            $sp
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
        $schoolId = Auth::schoolId() ?? 1;
        Database::query(
            "INSERT INTO announcements (school_id, user_id, title, body) VALUES (?, ?, ?, ?)",
            [$schoolId, Auth::user()['id'], $title, $body]
        );
        Flash::set('success', 'Announcement posted.');
        $this->redirect('/announcements'); return '';
    }
}
