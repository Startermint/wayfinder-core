<?php

declare(strict_types=1);

namespace Wayfinder\Exceptions;

use Throwable;
use Wayfinder\Http\Request;
use Wayfinder\Http\Response;

final class PrettyExceptionRenderer implements ExceptionRenderer
{
    public function render(Throwable $throwable, Request $request): Response
    {
        return Response::html($this->renderPage($throwable, $request), 500);
    }

    private function renderPage(Throwable $throwable, Request $request): string
    {
        $class = $throwable::class;
        $message = $throwable->getMessage() !== '' ? $throwable->getMessage() : 'No exception message provided.';
        $location = $throwable->getFile() . ':' . $throwable->getLine();

        return '<!doctype html>'
            . '<html lang="en">'
            . '<head>'
            . '<meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $this->e($class) . '</title>'
            . '<style>' . $this->styles() . '</style>'
            . '</head>'
            . '<body>'
            . '<main class="wf-shell">'
            . '<section class="wf-hero">'
            . '<div class="wf-kicker">Wayfinder Debug Exception</div>'
            . '<h1>' . $this->e($class) . '</h1>'
            . '<p class="wf-message">' . $this->e($message) . '</p>'
            . '<div class="wf-location">' . $this->e($location) . '</div>'
            . '</section>'
            . '<section class="wf-grid">'
            . '<article class="wf-panel wf-source">'
            . '<h2>Source</h2>'
            . $this->renderSource($throwable)
            . '</article>'
            . '<article class="wf-panel">'
            . '<h2>Request</h2>'
            . $this->renderRequest($request)
            . '</article>'
            . '</section>'
            . '<section class="wf-panel">'
            . '<h2>Stack Trace</h2>'
            . $this->renderTrace($throwable)
            . '</section>'
            . '</main>'
            . '</body>'
            . '</html>';
    }

    private function renderSource(Throwable $throwable): string
    {
        $file = $throwable->getFile();

        if ($file === '' || ! is_readable($file)) {
            return '<p class="wf-muted">Source file is not readable.</p>';
        }

        $lines = file($file);

        if ($lines === false) {
            return '<p class="wf-muted">Source file could not be loaded.</p>';
        }

        $current = $throwable->getLine();
        $start = max(1, $current - 6);
        $end = min(count($lines), $current + 6);
        $html = '<div class="wf-code" role="region" aria-label="Source excerpt">';

        for ($lineNumber = $start; $lineNumber <= $end; $lineNumber++) {
            $line = rtrim($lines[$lineNumber - 1] ?? '', "\r\n");
            $active = $lineNumber === $current ? ' wf-code-line-active' : '';
            $html .= '<div class="wf-code-line' . $active . '">'
                . '<span class="wf-code-number">' . $lineNumber . '</span>'
                . '<code>' . $this->e($line) . '</code>'
                . '</div>';
        }

        return $html . '</div>';
    }

    private function renderRequest(Request $request): string
    {
        $requestId = $request->header('x-request-id') ?? 'not provided';

        return '<dl class="wf-list">'
            . '<dt>Method</dt><dd>' . $this->e($request->method()) . '</dd>'
            . '<dt>Path</dt><dd>' . $this->e($request->path()) . '</dd>'
            . '<dt>Expects JSON</dt><dd>' . ($request->expectsJson() ? 'yes' : 'no') . '</dd>'
            . '<dt>Request ID</dt><dd>' . $this->e($requestId) . '</dd>'
            . '</dl>';
    }

    private function renderTrace(Throwable $throwable): string
    {
        $frames = [[
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'call' => 'throw',
        ]];

        foreach ($throwable->getTrace() as $frame) {
            $frames[] = [
                'file' => (string) ($frame['file'] ?? '[internal]'),
                'line' => (int) ($frame['line'] ?? 0),
                'call' => $this->frameCall($frame),
            ];
        }

        $html = '<ol class="wf-trace">';

        foreach ($frames as $index => $frame) {
            $line = $frame['line'] > 0 ? ':' . $frame['line'] : '';
            $html .= '<li>'
                . '<div class="wf-trace-index">#' . $index . '</div>'
                . '<div>'
                . '<div class="wf-trace-call">' . $this->e($frame['call']) . '</div>'
                . '<div class="wf-trace-file">' . $this->e($frame['file'] . $line) . '</div>'
                . '</div>'
                . '</li>';
        }

        return $html . '</ol>';
    }

    /**
     * @param array<string, mixed> $frame
     */
    private function frameCall(array $frame): string
    {
        $class = isset($frame['class']) ? (string) $frame['class'] : '';
        $type = isset($frame['type']) ? (string) $frame['type'] : '';
        $function = isset($frame['function']) ? (string) $frame['function'] : '[unknown]';

        return $class . $type . $function . '()';
    }

    private function styles(): string
    {
        return <<<'CSS'
:root {
  color-scheme: light;
  --bg: #f6f8fb;
  --panel: #ffffff;
  --text: #17202a;
  --muted: #637083;
  --line: #d9e1ec;
  --accent: #0b6bcb;
  --danger: #b3261e;
  --danger-bg: #fff1f0;
  --code-bg: #101828;
  --code-text: #e6edf7;
}
* { box-sizing: border-box; }
body {
  margin: 0;
  background: var(--bg);
  color: var(--text);
  font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}
.wf-shell {
  width: min(1180px, calc(100vw - 32px));
  margin: 0 auto;
  padding: 32px 0;
}
.wf-hero,
.wf-panel {
  background: var(--panel);
  border: 1px solid var(--line);
  border-radius: 8px;
  box-shadow: 0 14px 40px rgba(23, 32, 42, 0.08);
}
.wf-hero {
  padding: 28px;
  border-top: 4px solid var(--danger);
}
.wf-kicker {
  color: var(--danger);
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
}
h1,
h2,
p {
  margin: 0;
}
h1 {
  margin-top: 10px;
  font-size: clamp(1.7rem, 4vw, 3rem);
  line-height: 1.08;
  overflow-wrap: anywhere;
}
h2 {
  margin-bottom: 14px;
  font-size: 1rem;
}
.wf-message {
  margin-top: 14px;
  color: var(--muted);
  font-size: 1.05rem;
  overflow-wrap: anywhere;
}
.wf-location {
  display: inline-block;
  margin-top: 18px;
  padding: 8px 10px;
  max-width: 100%;
  overflow-wrap: anywhere;
  background: var(--danger-bg);
  border: 1px solid #ffd2cc;
  border-radius: 6px;
  color: var(--danger);
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.86rem;
}
.wf-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) minmax(280px, 0.7fr);
  gap: 18px;
  margin-top: 18px;
}
.wf-panel {
  margin-top: 18px;
  padding: 20px;
}
.wf-grid .wf-panel {
  margin-top: 0;
}
.wf-code {
  overflow-x: auto;
  background: var(--code-bg);
  border-radius: 8px;
  padding: 10px 0;
}
.wf-code-line {
  display: grid;
  grid-template-columns: 58px minmax(0, 1fr);
  min-height: 26px;
  color: var(--code-text);
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.86rem;
  line-height: 1.55;
}
.wf-code-line-active {
  background: rgba(179, 38, 30, 0.28);
}
.wf-code-number {
  color: #98a2b3;
  padding-right: 14px;
  text-align: right;
  user-select: none;
}
.wf-code code {
  white-space: pre;
  padding-right: 18px;
}
.wf-list {
  display: grid;
  grid-template-columns: 110px minmax(0, 1fr);
  gap: 10px 14px;
  margin: 0;
}
.wf-list dt {
  color: var(--muted);
  font-weight: 700;
}
.wf-list dd {
  margin: 0;
  overflow-wrap: anywhere;
}
.wf-trace {
  list-style: none;
  margin: 0;
  padding: 0;
}
.wf-trace li {
  display: grid;
  grid-template-columns: 54px minmax(0, 1fr);
  gap: 12px;
  padding: 12px 0;
  border-top: 1px solid var(--line);
}
.wf-trace li:first-child {
  border-top: 0;
}
.wf-trace-index {
  color: var(--accent);
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-weight: 700;
}
.wf-trace-call,
.wf-trace-file {
  overflow-wrap: anywhere;
}
.wf-trace-call {
  font-weight: 700;
}
.wf-trace-file,
.wf-muted {
  color: var(--muted);
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.86rem;
}
@media (max-width: 820px) {
  .wf-shell {
    width: min(100vw - 20px, 1180px);
    padding: 10px 0;
  }
  .wf-grid {
    grid-template-columns: 1fr;
  }
  .wf-hero,
  .wf-panel {
    padding: 16px;
  }
}
CSS;
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
