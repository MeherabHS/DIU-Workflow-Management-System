<?php

namespace App\Services;

use App\Models\WorkflowFile;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use PhpOffice\PhpWord\IOFactory as PhpWordIOFactory;
use PhpOffice\PhpSpreadsheet\IOFactory as PhpSpreadsheetIOFactory;

class FileTextExtractionService
{
    /**
     * Extract text from a workflow file.
     * Returns ['text' => string, 'error' => string|null]
     */
    public function extractText(WorkflowFile $file): array
    {
        $disk = $file->disk;
        $path = $file->path;

        if (! Storage::disk($disk)->exists($path)) {
            return ['text' => '', 'error' => 'File not found on disk.'];
        }

        $extension = strtolower(pathinfo($file->original_name, PATHINFO_EXTENSION));

        return match ($extension) {
            'txt' => $this->extractTxt($disk, $path),
            'csv' => $this->extractCsv($disk, $path),
            'pdf' => $this->extractPdf($disk, $path),
            'docx' => $this->extractDocx($disk, $path),
            'xlsx' => $this->extractXlsx($disk, $path),
            default => ['text' => '', 'error' => "Unsupported file type: {$extension}"],
        };
    }

    protected function extractTxt(string $disk, string $path): array
    {
        $content = Storage::disk($disk)->get($path);

        return ['text' => $content !== null ? $content : '', 'error' => null];
    }

    protected function extractCsv(string $disk, string $path): array
    {
        $content = Storage::disk($disk)->get($path);

        return ['text' => $content !== null ? $content : '', 'error' => null];
    }

    protected function extractPdf(string $disk, string $path): array
    {
        try {
            $fullPath = Storage::disk($disk)->path($path);
            $parser = new Parser();
            $pdf = $parser->parseFile($fullPath);
            $text = trim($pdf->getText());

            if ($text === '') {
                return [
                    'text' => '',
                    'error' => 'No readable text found. Scanned PDFs are not supported yet.',
                ];
            }

            return ['text' => $text, 'error' => null];
        } catch (\Exception $e) {
            return ['text' => '', 'error' => "PDF text extraction failed: {$e->getMessage()}"];
        }
    }

    protected function extractDocx(string $disk, string $path): array
    {
        try {
            $fullPath = Storage::disk($disk)->path($path);
            $phpWord = PhpWordIOFactory::load($fullPath);
            $text = '';

            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        $text .= $element->getText() . "\n";
                    }
                }
            }

            return ['text' => trim($text), 'error' => null];
        } catch (\Exception $e) {
            return ['text' => '', 'error' => "DOCX text extraction failed: {$e->getMessage()}"];
        }
    }

    protected function extractXlsx(string $disk, string $path): array
    {
        try {
            $fullPath = Storage::disk($disk)->path($path);
            $spreadsheet = PhpSpreadsheetIOFactory::load($fullPath);
            $text = '';

            foreach ($spreadsheet->getAllSheets() as $sheet) {
                $text .= "Sheet: " . $sheet->getTitle() . "\n";
                foreach ($sheet->getRowIterator() as $row) {
                    $rowText = [];
                    foreach ($row->getCellIterator() as $cell) {
                        $rowText[] = $cell->getValue();
                    }
                    if (implode('', $rowText) !== '') {
                        $text .= implode(' | ', $rowText) . "\n";
                    }
                }
                $text .= "\n";
            }

            return ['text' => trim($text), 'error' => null];
        } catch (\Exception $e) {
            return ['text' => '', 'error' => "XLSX text extraction failed: {$e->getMessage()}"];
        }
    }
}
