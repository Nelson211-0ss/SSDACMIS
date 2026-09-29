<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Settings;
use Throwable;

class LandingController extends Controller
{
    /**
     * How many photos the hero slider rotates through. Every slide is
     * fetched up front (see the view), so this also caps what the landing
     * page costs a visitor on a slow connection.
     */
    private const SLIDE_LIMIT = 5;

    public function index(): string
    {
        return $this->view('landing/index', [
            'title'  => 'SSD-ACMIS — School Management System',
            'slides' => $this->heroSlides(),
        ]);
    }

    /**
     * Student passport photos for the hero slider.
     *
     * This page is public — no sign-in — so the query is deliberately
     * narrow: the photo path and nothing else. No name, admission number,
     * class or school comes back with it, so a visitor sees faces but can
     * identify nobody from the page.
     *
     * An administrator can switch the whole thing off in Settings
     * ('landing_student_photos'), and with no photos uploaded the slider
     * simply isn't rendered.
     *
     * @return list<string> public-relative image paths
     */
    private function heroSlides(): array
    {
        try {
            Settings::ensureTable();
            if (Settings::get('landing_student_photos') !== '1') {
                return [];
            }

            $rows = Database::query(
                "SELECT photo_path FROM students
                 WHERE photo_path IS NOT NULL AND photo_path <> ''
                 ORDER BY RAND()
                 LIMIT " . self::SLIDE_LIMIT
            )->fetchAll();
        } catch (Throwable $e) {
            // The landing page must render even before the database exists
            // (fresh clone, first run) — it is the route people hit first.
            return [];
        }

        $root   = dirname(__DIR__, 2) . '/public/';
        $slides = [];
        foreach ($rows as $r) {
            $rel = ltrim(trim((string) ($r['photo_path'] ?? '')), '/');
            // Uploads only, no traversal, and the file has to actually exist.
            if ($rel === '' || !str_starts_with($rel, 'uploads/') || str_contains($rel, '..')) {
                continue;
            }
            if (is_file($root . $rel)) {
                $slides[] = $rel;
            }
        }

        return $slides;
    }
}
