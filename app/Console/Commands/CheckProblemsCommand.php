<?php

namespace App\Console\Commands;

use App\Models\Part;
use App\Models\Piece;
use App\Models\Speelperiode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CheckProblemsCommand extends Command
{
    protected $signature = 'music:check-problems';

    protected $description = 'Check for data integrity problems in the music library';

    public function handle(): int
    {
        $totalProblems = 0;

        $totalProblems += $this->checkPartsWithDeletedInstrumentType();
        $totalProblems += $this->checkSpeelperiodesWithDeletedOrchestra();
        $totalProblems += $this->checkMissingFiles();
        $totalProblems += $this->checkPiecesWithoutParts();

        $this->newLine();

        if ($totalProblems === 0) {
            $this->info('No problems found.');

            return self::SUCCESS;
        }

        $this->error("Found {$totalProblems} problem(s).");

        return self::FAILURE;
    }

    private function checkPartsWithDeletedInstrumentType(): int
    {
        $parts = Part::query()
            ->whereHas('instrumentType', fn ($q) => $q->onlyTrashed())
            ->with(['piece:id,title', 'instrumentType'])
            ->get();

        if ($parts->isEmpty()) {
            return 0;
        }

        $this->warn('Parts with deleted instrument type:');
        $this->table(
            ['Part ID', 'Piece', 'Instrument Type', 'Deleted At'],
            $parts->map(fn (Part $part) => [
                $part->id,
                $part->piece?->title ?? '—',
                $part->instrumentType?->name ?? '—',
                $part->instrumentType?->deleted_at?->toDateTimeString() ?? '—',
            ]),
        );

        return $parts->count();
    }

    private function checkSpeelperiodesWithDeletedOrchestra(): int
    {
        $speelperiodes = Speelperiode::query()
            ->whereHas('orchestra', fn ($q) => $q->onlyTrashed())
            ->with(['piece:id,title', 'orchestra'])
            ->get();

        if ($speelperiodes->isEmpty()) {
            return 0;
        }

        $this->warn('Speelperiodes with deleted orchestra:');
        $this->table(
            ['Piece', 'Orchestra', 'Deleted At'],
            $speelperiodes->map(fn (Speelperiode $speelperiode) => [
                $speelperiode->piece?->title ?? '—',
                $speelperiode->orchestra?->name ?? '—',
                $speelperiode->orchestra?->deleted_at?->toDateTimeString() ?? '—',
            ]),
        );

        return $speelperiodes->count();
    }

    private function checkMissingFiles(): int
    {
        $disk = Storage::disk('sheets');

        $parts = Part::query()
            ->whereNotNull('file_path')
            ->where('file_path', '!=', '')
            ->get(['id', 'piece_id', 'file_path']);

        $missing = $parts->filter(fn (Part $part) => ! $disk->exists($part->file_path));

        if ($missing->isEmpty()) {
            return 0;
        }

        $missing->load('piece:id,title');

        $this->warn('Parts with missing files:');
        $this->table(
            ['Part ID', 'Piece', 'File Path'],
            $missing->map(fn (Part $part) => [
                $part->id,
                $part->piece?->title ?? '—',
                $part->file_path,
            ]),
        );

        return $missing->count();
    }

    private function checkPiecesWithoutParts(): int
    {
        $pieces = Piece::query()
            ->doesntHave('parts')
            ->get(['id', 'title']);

        if ($pieces->isEmpty()) {
            return 0;
        }

        $this->warn('Pieces without parts:');
        $this->table(
            ['Piece ID', 'Title'],
            $pieces->map(fn (Piece $piece) => [
                $piece->id,
                $piece->title,
            ]),
        );

        return $pieces->count();
    }
}
