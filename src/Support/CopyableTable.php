<?php

namespace CharlesStOlive\FilamentTma\Support;

/**
 * Un tableau à coller ailleurs (un courriel, un document, un ticket) : un titre en gras, une phrase, puis le tableau,
 * en HTML (styles en ligne, pour qu'un courriel le garde) ou en Markdown (le code). Une ligne peut être en gras (une
 * somme), une autre en retrait (son détail, en HTML seulement : le Markdown n'en a pas dans un tableau).
 */
final class CopyableTable
{
    private const CELL_STYLE = 'border:1px solid #d1d5db;padding:4px 8px;';

    /** @var array<int, array{cells: array<int, ?string>, strong: bool, indent: bool}> */
    private array $rows = [];

    /** @param  array<int, array{label: string, end?: bool}>  $columns  `end` : aligné à droite (une durée) */
    public function __construct(
        private readonly string $title,
        private readonly string $caption,
        private readonly array $columns,
    ) {}

    /** @param  array<int, ?string>  $cells  dans l'ordre des colonnes */
    public function row(array $cells, bool $strong = false, bool $indent = false): self
    {
        $this->rows[] = ['cells' => array_values($cells), 'strong' => $strong, 'indent' => $indent];

        return $this;
    }

    public function html(): string
    {
        $head = collect($this->columns)
            ->map(fn (array $column): string => '<th style="'.self::CELL_STYLE.'text-align:'.(($column['end'] ?? false) ? 'right' : 'left').';">'.e($column['label']).'</th>')
            ->implode('');

        $body = collect($this->rows)->map(function (array $row): string {
            $cells = collect($this->columns)->map(function (array $column, int $index) use ($row): string {
                $text = e($row['cells'][$index] ?? '');
                $style = self::CELL_STYLE.(($column['end'] ?? false) ? 'text-align:right;' : '').($index === 0 && $row['indent'] ? 'padding-left:24px;' : '');

                return '<td style="'.$style.'">'.($row['strong'] && $text !== '' ? '<strong>'.$text.'</strong>' : $text).'</td>';
            });

            return '<tr>'.$cells->implode('').'</tr>';
        })->implode("\n");

        return '<p><strong>'.e($this->title).'</strong><br>'.e($this->caption)."</p>\n"
            ."<table style=\"border-collapse:collapse;\">\n<thead><tr>{$head}</tr></thead>\n<tbody>\n{$body}\n</tbody>\n</table>";
    }

    public function markdown(): string
    {
        $lines = [
            '**'.self::escape($this->title).'**',
            '',
            self::escape($this->caption),
            '',
            '| '.collect($this->columns)->map(fn (array $column): string => self::escape($column['label']))->implode(' | ').' |',
            '| '.collect($this->columns)->map(fn (array $column): string => ($column['end'] ?? false) ? '---:' : '---')->implode(' | ').' |',
        ];

        foreach ($this->rows as $row) {
            $lines[] = '| '.collect($this->columns)->keys()->map(function (int $index) use ($row): string {
                $text = self::escape($row['cells'][$index] ?? '');

                return $row['strong'] && $text !== '' ? '**'.$text.'**' : $text;
            })->implode(' | ').' |';
        }

        return implode("\n", $lines);
    }

    /** Sur une ligne, sans ce qui ferait une mise en forme ou couperait le tableau (`|`). */
    private static function escape(string $text): string
    {
        return addcslashes(trim((string) preg_replace('/\s+/u', ' ', $text)), '\\|*_`<');
    }
}
