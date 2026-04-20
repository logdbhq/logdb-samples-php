<?php

declare(strict_types=1);

namespace Sample;

/**
 * Tiny `*.phtml` renderer. Produces the layout-wrapped HTML for a screen.
 *
 * Templates receive an associative `$data` array unpacked into local
 * variables, plus a `$view` reference if they need to render partials.
 */
final class View
{
    public function __construct(
        private readonly string $templatesDir,
    ) {
    }

    /**
     * Render `<screen>.phtml` inside `layout.phtml`.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $screen, array $data = []): string
    {
        $content = $this->capture("{$screen}.phtml", $data);

        // Layout gets everything the controller passed (so partials like the
        // console can reach `$history`) plus the rendered content + a default
        // title so templates that don't supply one still produce a valid <title>.
        return $this->capture('layout.phtml', $data + [
            'title' => 'LogDB PHP Sample',
            'active' => '',
            'flash' => null,
            'history' => null,
            'content' => $content,
        ]);
    }

    /**
     * Render a partial fragment without the layout wrap.
     *
     * @param array<string, mixed> $data
     */
    public function partial(string $relativePath, array $data = []): string
    {
        return $this->capture($relativePath, $data);
    }

    /** @param array<string, mixed> $data */
    private function capture(string $relativePath, array $data): string
    {
        $file = $this->templatesDir . DIRECTORY_SEPARATOR . $relativePath;
        if (!is_file($file)) {
            throw new \RuntimeException("Template not found: {$relativePath}");
        }

        $view = $this;
        $data['view'] = $this;
        extract($data, EXTR_SKIP);

        ob_start();
        try {
            require $file;
            $captured = ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return $captured === false ? '' : $captured;
    }

    /** Convenience HTML escape. Use as `<?= $view->e($value) ?>`. */
    public function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
