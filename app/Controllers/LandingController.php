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
     * Images shipped with the project, used whenever there are no student
     * photos to show. Most installs never upload a passport photo — the CSV
     * importer doesn't set one — so without this the hero would simply have
     * an empty column on a perfectly healthy system.
     */
    private const FALLBACK_SLIDES = [
        'assets/img/login-slide-1.jpg',
        'assets/img/login-slide-2.jpg',
        'assets/img/login-slide-3.jpg',
        'assets/img/login-slide-4.jpg',
        'assets/img/login-hero.jpg',
    ];

    /**
     * Pictures for the hero slider: real student photos where they exist,
     * otherwise the bundled school images.
     *
     * This page is public — no sign-in — so the student query is
     * deliberately narrow: the photo path and nothing else. No name,
     * admission number, class or school comes back with it, so a visitor
     * sees faces but can identify nobody from the page. An administrator
     * can switch student photos off in Settings ('landing_student_photos'),
     * which drops the page back to the bundled images rather than leaving
     * the hero half empty.
     *
     * @return list<string> public-relative image paths
     */
    private function heroSlides(): array
    {
        $slides = $this->studentSlides();

        return $slides !== [] ? $slides : $this->existingFiles(self::FALLBACK_SLIDES);
    }

    /**
     * @return list<string> public-relative paths of uploaded student photos
     */
    private function studentSlides(): array
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

        $paths = [];
        foreach ($rows as $r) {
            $rel = ltrim(trim((string) ($r['photo_path'] ?? '')), '/');
            // Uploads only, and no traversal out of /public.
            if ($rel === '' || !str_starts_with($rel, 'uploads/') || str_contains($rel, '..')) {
                continue;
            }
            $paths[] = $rel;
        }

        return $this->existingFiles($paths);
    }

    /**
     * Keep only the paths that are actually on disk — a photo row can
     * outlive its file, and a missing image would show as a blank slide.
     *
     * @param  list<string> $paths
     * @return list<string>
     */
    private function existingFiles(array $paths): array
    {
        $root = dirname(__DIR__, 2) . '/public/';
        $out  = [];
        foreach ($paths as $rel) {
            if (is_file($root . $rel)) {
                $out[] = $rel;
            }
        }

        return $out;
    }
}
