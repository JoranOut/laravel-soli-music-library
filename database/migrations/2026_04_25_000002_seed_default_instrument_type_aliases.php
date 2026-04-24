<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Map: DB instrument name → aliases (lowercase, excluding the name itself)
        $aliasMap = [
            'Contrabassaxofoon' => [],
            'Sopraansaxofoon' => ['sopraansax', 'soprano sax'],
            'Baritonsaxofoon' => ['baritonsax', 'bariton sax', 'bari sax'],
            'Tenorsaxofoon' => ['tenorsax', 'tenor sax'],
            'Bassaxofoon' => ['bass sax'],
            'Altsaxofoon' => ['altsax', 'alt sax', 'alto sax'],
            'Saxofoon' => ['saxophone'],
            'Contrabasklarinet' => ['contrabass clarinet'],
            'Basklarinet' => ['bass clarinet'],
            'Altklarinet' => ['alto clarinet'],
            'Besklarinet' => ['clar bb', 'clarinet bb', 'bb clarinet', 'klarinet bes'],
            'Esklarinet' => ['clar eb', 'clarinet eb', 'eb clarinet'],
            'Klarinet' => ['clarinet', 'clar'],
            'Trompet' => ['trumpet', 'trp'],
            'Cornet' => [],
            'Bugel' => ['bugle', 'flugelhorn', 'flugel'],
            'Bastrombone' => ['bass trombone'],
            'Trombone' => ['trb'],
            'Althoorn' => ['alto horn'],
            'Hoorn' => ['french horn', 'hoorn f', 'horn in f', 'horn'],
            'Sousafoon' => ['sousaphone'],
            'Contrabas' => ['contrabass', 'string bass'],
            'Besbas' => ['bb bass', 'bb-bass', 'bes bas', 'bas bes'],
            'Esbas' => ['eb bass', 'eb-bass', 'es bas', 'bas eb'],
            'Tuba' => [],
            'Euphonium' => [],
            'Bariton' => ['baritone', 'bar'],
            'Dwarsfluit' => ['flute'],
            'Piccolo' => [],
            'Althobo' => ['english horn', 'cor anglais'],
            'Hobo' => ['oboe'],
            'Contrafagot' => ['contrabassoon'],
            'Fagot' => ['bassoon'],
            'Melodisch slagwerk' => [],
            'Kleine trom' => ['snare drum', 'snare'],
            'Paradetrom' => [],
            'Trio tom' => [],
            'Vibrafoon' => ['vibraphone'],
            'Xylofoon' => ['xylophone'],
            'Marimba' => [],
            'Pauken' => ['timpani'],
            'Bekken' => ['cymbals'],
            'Percussion' => [],
            'Slagwerk' => ['drums', 'drumstel'],
            'Trom' => ['drum'],
            'Basgitaar' => ['bass guitar'],
            'Gitaar' => ['guitar'],
            'Keyboard' => [],
            'Piano' => [],
            'Zang' => ['vocal'],
            'Tamboer-maître' => ['tamboer-maitre', 'tamboer maitre', 'drum major'],
            'Majorette' => [],
            'Vlaggenwacht' => [],
            'Partituur' => [],
        ];

        foreach ($aliasMap as $name => $aliases) {
            if (empty($aliases)) {
                continue;
            }

            DB::table('instrument_types')
                ->where('name', $name)
                ->update(['aliases' => json_encode($aliases)]);
        }
    }

    public function down(): void
    {
        DB::table('instrument_types')->update(['aliases' => '[]']);
    }
};
