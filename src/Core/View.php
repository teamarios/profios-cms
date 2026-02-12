<?php

namespace App\Core;

final class View
{
    public static function render(string $view, array $data = [], string $layout = 'frontend'): void
    {
        $viewFile = VIEW_PATH . '/' . str_replace('.', '/', $view) . '.php';
        $layoutFile = VIEW_PATH . '/layouts/' . $layout . '.php';

        if (!is_file($viewFile) || !is_file($layoutFile)) {
            http_response_code(500);
            echo 'View file missing.';
            return;
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();

        require $layoutFile;
    }
}
