<?php

use App\Models\DownloadLog;
use App\Models\InstrumentType;
use App\Models\Orchestra;
use App\Models\Part;
use App\Models\Piece;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Storage::fake('sheets');
});

it('uploads a single part', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();
    $instrumentType = InstrumentType::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->post("/muziekstukken/{$piece->id}/parts", [
            'parts' => [
                [
                    'file' => UploadedFile::fake()->create('clarinet.pdf', 100, 'application/pdf'),
                    'instrument_type_id' => $instrumentType->id,
                    'is_conductor' => false,
                ],
            ],
        ])
        ->assertRedirect();

    expect($piece->parts()->count())->toBe(1);

    $part = $piece->parts->first();
    Storage::disk('sheets')->assertExists($part->file_path);
    expect($part->original_filename)->toBe('clarinet.pdf');
    expect($part->instrument_type_id)->toBe($instrumentType->id);
    expect($part->is_conductor)->toBeFalse();
});

it('uploads multiple parts at once', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();
    $type1 = InstrumentType::factory()->create();
    $type2 = InstrumentType::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->post("/muziekstukken/{$piece->id}/parts", [
            'parts' => [
                [
                    'file' => UploadedFile::fake()->create('part1.pdf', 100, 'application/pdf'),
                    'instrument_type_id' => $type1->id,
                ],
                [
                    'file' => UploadedFile::fake()->create('part2.pdf', 100, 'application/pdf'),
                    'instrument_type_id' => $type2->id,
                    'is_conductor' => true,
                ],
            ],
        ])
        ->assertRedirect();

    expect($piece->parts()->count())->toBe(2);
});

it('rejects non-PDF files', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();
    $instrumentType = InstrumentType::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->post("/muziekstukken/{$piece->id}/parts", [
            'parts' => [
                [
                    'file' => UploadedFile::fake()->create('image.png', 100, 'image/png'),
                    'instrument_type_id' => $instrumentType->id,
                ],
            ],
        ])
        ->assertSessionHasErrors('parts.0.file');
});

it('deletes a part and removes the file from disk', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();
    $instrumentType = InstrumentType::factory()->create();

    // Upload first
    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->post("/muziekstukken/{$piece->id}/parts", [
            'parts' => [
                [
                    'file' => UploadedFile::fake()->create('test.pdf', 100, 'application/pdf'),
                    'instrument_type_id' => $instrumentType->id,
                ],
            ],
        ]);

    $part = $piece->parts()->first();
    $filePath = $part->file_path;
    Storage::disk('sheets')->assertExists($filePath);

    // Delete
    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->delete("/muziekstukken/{$piece->id}/parts/{$part->id}")
        ->assertRedirect();

    expect(Part::find($part->id))->toBeNull();
    Storage::disk('sheets')->assertMissing($filePath);
});

it('returns 404 when deleting a part from a different piece', function () {
    $user = User::factory()->create();
    $piece1 = Piece::factory()->create();
    $piece2 = Piece::factory()->create();
    $part = Part::factory()->create(['piece_id' => $piece1->id]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->delete("/muziekstukken/{$piece2->id}/parts/{$part->id}")
        ->assertNotFound();
});

// ---------------------------------------------------------------------------
// Guest access — must redirect to login, no side effects
// ---------------------------------------------------------------------------

it('redirects guests from part upload', function () {
    $piece = Piece::factory()->create();
    $instrumentType = InstrumentType::factory()->create();

    $this->post("/muziekstukken/{$piece->id}/parts", [
        'parts' => [
            [
                'file' => UploadedFile::fake()->create('hack.pdf', 100, 'application/pdf'),
                'instrument_type_id' => $instrumentType->id,
            ],
        ],
    ])->assertRedirect('/auth/redirect');

    expect($piece->parts()->count())->toBe(0);
    Storage::disk('sheets')->assertDirectoryEmpty('.');
});

it('redirects guests from part delete', function () {
    $piece = Piece::factory()->create();
    $part = Part::factory()->create(['piece_id' => $piece->id]);

    $this->delete("/muziekstukken/{$piece->id}/parts/{$part->id}")
        ->assertRedirect('/auth/redirect');

    expect(Part::find($part->id))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Non-editor roles — must return 403, no side effects
// ---------------------------------------------------------------------------

it('denies non-editor users from uploading parts', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();
    $instrumentType = InstrumentType::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['member']])
        ->post("/muziekstukken/{$piece->id}/parts", [
            'parts' => [
                [
                    'file' => UploadedFile::fake()->create('test.pdf', 100, 'application/pdf'),
                    'instrument_type_id' => $instrumentType->id,
                ],
            ],
        ])
        ->assertForbidden();

    expect($piece->parts()->count())->toBe(0);
});

it('denies non-editor users from deleting parts', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();
    $part = Part::factory()->create(['piece_id' => $piece->id]);

    $this->actingAs($user)
        ->withSession(['roles' => ['member']])
        ->delete("/muziekstukken/{$piece->id}/parts/{$part->id}")
        ->assertForbidden();

    expect(Part::find($part->id))->not->toBeNull();
});

it('denies users with no roles from part routes', function () {
    $user = User::factory()->create(['oidc_roles' => []]);
    $piece = Piece::factory()->create();
    $instrumentType = InstrumentType::factory()->create();
    $part = Part::factory()->create(['piece_id' => $piece->id]);

    $this->actingAs($user)
        ->post("/muziekstukken/{$piece->id}/parts", [
            'parts' => [
                [
                    'file' => UploadedFile::fake()->create('test.pdf', 100, 'application/pdf'),
                    'instrument_type_id' => $instrumentType->id,
                ],
            ],
        ])
        ->assertForbidden();

    $this->actingAs($user)
        ->delete("/muziekstukken/{$piece->id}/parts/{$part->id}")
        ->assertForbidden();

    expect($piece->parts()->count())->toBe(1);
});

it('denies users with empty roles array from part routes', function () {
    $user = User::factory()->create(['oidc_roles' => []]);
    $piece = Piece::factory()->create();
    $instrumentType = InstrumentType::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => []])
        ->post("/muziekstukken/{$piece->id}/parts", [
            'parts' => [
                [
                    'file' => UploadedFile::fake()->create('test.pdf', 100, 'application/pdf'),
                    'instrument_type_id' => $instrumentType->id,
                ],
            ],
        ])
        ->assertForbidden();

    expect($piece->parts()->count())->toBe(0);
});

it('denies unrelated roles from part routes', function (string $role) {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();
    $instrumentType = InstrumentType::factory()->create();
    $part = Part::factory()->create(['piece_id' => $piece->id]);

    $session = ['roles' => [$role]];

    $this->actingAs($user)->withSession($session)
        ->post("/muziekstukken/{$piece->id}/parts", [
            'parts' => [
                [
                    'file' => UploadedFile::fake()->create('test.pdf', 100, 'application/pdf'),
                    'instrument_type_id' => $instrumentType->id,
                ],
            ],
        ])
        ->assertForbidden();

    $this->actingAs($user)->withSession($session)
        ->delete("/muziekstukken/{$piece->id}/parts/{$part->id}")
        ->assertForbidden();

    expect($piece->parts()->count())->toBe(1);
})->with(['member', 'lid', 'dirigent', 'vrijwilliger']);

// ---------------------------------------------------------------------------
// Allowed editor roles — verify all three can upload/delete
// ---------------------------------------------------------------------------

it('allows all editor roles to upload and delete parts', function (string $role) {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();
    $instrumentType = InstrumentType::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => [$role]])
        ->post("/muziekstukken/{$piece->id}/parts", [
            'parts' => [
                [
                    'file' => UploadedFile::fake()->create('score.pdf', 100, 'application/pdf'),
                    'instrument_type_id' => $instrumentType->id,
                ],
            ],
        ])
        ->assertRedirect();

    $part = $piece->parts()->first();
    expect($part)->not->toBeNull();

    $this->actingAs($user)
        ->withSession(['roles' => [$role]])
        ->delete("/muziekstukken/{$piece->id}/parts/{$part->id}")
        ->assertRedirect();

    expect(Part::find($part->id))->toBeNull();
})->with(['admin', 'muziekbeheer']);

// ---------------------------------------------------------------------------
// Update part — instrument type and is_conductor
// ---------------------------------------------------------------------------

it('updates instrument_type_id on a part', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();
    $oldType = InstrumentType::factory()->create();
    $newType = InstrumentType::factory()->create();
    $part = Part::factory()->create([
        'piece_id' => $piece->id,
        'instrument_type_id' => $oldType->id,
        'is_conductor' => false,
    ]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->put("/muziekstukken/{$piece->id}/parts/{$part->id}", [
            'instrument_type_id' => $newType->id,
            'is_conductor' => false,
        ])
        ->assertRedirect();

    $part->refresh();
    expect($part->instrument_type_id)->toBe($newType->id);
});

it('updates is_conductor flag on a part', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();
    $part = Part::factory()->create([
        'piece_id' => $piece->id,
        'is_conductor' => false,
    ]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->put("/muziekstukken/{$piece->id}/parts/{$part->id}", [
            'instrument_type_id' => $part->instrument_type_id,
            'is_conductor' => true,
        ])
        ->assertRedirect();

    $part->refresh();
    expect($part->is_conductor)->toBeTrue();
});

it('validates instrument_type_id is required when updating a part', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();
    $part = Part::factory()->create(['piece_id' => $piece->id]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->put("/muziekstukken/{$piece->id}/parts/{$part->id}", [
            'is_conductor' => false,
        ])
        ->assertSessionHasErrors('instrument_type_id');
});

it('validates instrument_type_id exists when updating a part', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();
    $part = Part::factory()->create(['piece_id' => $piece->id]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->put("/muziekstukken/{$piece->id}/parts/{$part->id}", [
            'instrument_type_id' => 99999,
            'is_conductor' => false,
        ])
        ->assertSessionHasErrors('instrument_type_id');
});

it('returns 404 when updating a part from a different piece', function () {
    $user = User::factory()->create();
    $piece1 = Piece::factory()->create();
    $piece2 = Piece::factory()->create();
    $part = Part::factory()->create(['piece_id' => $piece1->id]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->put("/muziekstukken/{$piece2->id}/parts/{$part->id}", [
            'instrument_type_id' => $part->instrument_type_id,
            'is_conductor' => false,
        ])
        ->assertNotFound();
});

it('redirects guests from part update', function () {
    $piece = Piece::factory()->create();
    $part = Part::factory()->create(['piece_id' => $piece->id]);
    $newType = InstrumentType::factory()->create();

    $this->put("/muziekstukken/{$piece->id}/parts/{$part->id}", [
        'instrument_type_id' => $newType->id,
        'is_conductor' => false,
    ])->assertRedirect('/auth/redirect');

    $part->refresh();
    expect($part->instrument_type_id)->not->toBe($newType->id);
});

it('denies non-editor users from updating parts', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();
    $part = Part::factory()->create(['piece_id' => $piece->id]);
    $newType = InstrumentType::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['member']])
        ->put("/muziekstukken/{$piece->id}/parts/{$part->id}", [
            'instrument_type_id' => $newType->id,
            'is_conductor' => false,
        ])
        ->assertForbidden();

    $part->refresh();
    expect($part->instrument_type_id)->not->toBe($newType->id);
});

it('denies unrelated roles from updating parts', function (string $role) {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();
    $part = Part::factory()->create(['piece_id' => $piece->id]);
    $newType = InstrumentType::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => [$role]])
        ->put("/muziekstukken/{$piece->id}/parts/{$part->id}", [
            'instrument_type_id' => $newType->id,
            'is_conductor' => false,
        ])
        ->assertForbidden();

    $part->refresh();
    expect($part->instrument_type_id)->not->toBe($newType->id);
})->with(['member', 'lid', 'dirigent', 'vrijwilliger']);

it('allows all editor roles to update parts', function (string $role) {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();
    $newType = InstrumentType::factory()->create();
    $part = Part::factory()->create(['piece_id' => $piece->id]);

    $this->actingAs($user)
        ->withSession(['roles' => [$role]])
        ->put("/muziekstukken/{$piece->id}/parts/{$part->id}", [
            'instrument_type_id' => $newType->id,
            'is_conductor' => true,
        ])
        ->assertRedirect();

    $part->refresh();
    expect($part->instrument_type_id)->toBe($newType->id);
    expect($part->is_conductor)->toBeTrue();
})->with(['admin', 'muziekbeheer']);

// ---------------------------------------------------------------------------
// Download — access control, signed URLs, and logging
// ---------------------------------------------------------------------------

it('allows editor to download any part', function () {
    $user = User::factory()->create();
    Permission::findOrCreate('download-all partijen');
    $user->givePermissionTo('download-all partijen');
    $piece = Piece::factory()->create();
    $part = Part::factory()->create(['piece_id' => $piece->id]);

    Storage::disk('sheets')->put($part->file_path, 'pdf-content');

    $url = URL::temporarySignedRoute('parts.download', now()->addDay(), ['part' => $part->id]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get($url)
        ->assertOk();
});

it('allows dirigent with download-score permission to download partituur parts', function () {
    $user = User::factory()->create();
    Permission::findOrCreate('download-score partijen');
    $user->givePermissionTo('download-score partijen');

    $piece = Piece::factory()->create();
    $part = Part::factory()->partituur()->create(['piece_id' => $piece->id]);

    Storage::disk('sheets')->put($part->file_path, 'pdf-content');

    $url = URL::temporarySignedRoute('parts.download', now()->addDay(), ['part' => $part->id]);

    $this->actingAs($user)
        ->withSession(['roles' => ['dirigent']])
        ->get($url)
        ->assertOk();
});

it('denies dirigent without download-score permission from downloading partituur parts', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();
    $part = Part::factory()->partituur()->create(['piece_id' => $piece->id]);

    Storage::disk('sheets')->put($part->file_path, 'pdf-content');

    $url = URL::temporarySignedRoute('parts.download', now()->addDay(), ['part' => $part->id]);

    $this->actingAs($user)
        ->withSession(['roles' => ['dirigent']])
        ->get($url)
        ->assertForbidden();
});

it('denies dirigent download for non-partituur parts', function () {
    $user = User::factory()->create();
    Permission::findOrCreate('download-score partijen');
    $user->givePermissionTo('download-score partijen');
    $piece = Piece::factory()->create();
    $part = Part::factory()->create(['piece_id' => $piece->id, 'is_conductor' => false]);

    Storage::disk('sheets')->put($part->file_path, 'pdf-content');

    $url = URL::temporarySignedRoute('parts.download', now()->addDay(), ['part' => $part->id]);

    $this->actingAs($user)
        ->withSession(['roles' => ['dirigent']])
        ->get($url)
        ->assertForbidden();
});

it('allows member with matching assignment to download', function () {
    $user = User::factory()->create();
    Permission::findOrCreate('download-assigned partijen');
    $user->givePermissionTo('download-assigned partijen');
    $orchestra = Orchestra::factory()->create();
    $instrumentType = InstrumentType::factory()->create();

    $piece = Piece::factory()->create();
    $piece->speelperiodes()->create(['van' => now(), 'orchestra_id' => $orchestra->id]);
    $part = Part::factory()->create([
        'piece_id' => $piece->id,
        'instrument_type_id' => $instrumentType->id,
    ]);

    Storage::disk('sheets')->put($part->file_path, 'pdf-content');

    $url = URL::temporarySignedRoute('parts.download', now()->addDay(), ['part' => $part->id]);

    $session = [
        'roles' => ['member'],
        'resolved_assignments' => [
            ['orchestra_id' => $orchestra->id, 'instrument_type_id' => $instrumentType->id],
        ],
    ];

    $this->actingAs($user)
        ->withSession($session)
        ->get($url)
        ->assertOk();
});

it('denies member without matching assignment from downloading', function () {
    $user = User::factory()->create();
    Permission::findOrCreate('download-assigned partijen');
    $user->givePermissionTo('download-assigned partijen');
    $orchestra = Orchestra::factory()->create();
    $otherOrchestra = Orchestra::factory()->create();
    $instrumentType = InstrumentType::factory()->create();

    $piece = Piece::factory()->create();
    $piece->speelperiodes()->create(['van' => now(), 'orchestra_id' => $orchestra->id]);
    $part = Part::factory()->create([
        'piece_id' => $piece->id,
        'instrument_type_id' => $instrumentType->id,
    ]);

    Storage::disk('sheets')->put($part->file_path, 'pdf-content');

    $url = URL::temporarySignedRoute('parts.download', now()->addDay(), ['part' => $part->id]);

    // Member has assignment for a different orchestra
    $session = [
        'roles' => ['member'],
        'resolved_assignments' => [
            ['orchestra_id' => $otherOrchestra->id, 'instrument_type_id' => $instrumentType->id],
        ],
    ];

    $this->actingAs($user)
        ->withSession($session)
        ->get($url)
        ->assertForbidden();
});

it('redirects guests on download', function () {
    $part = Part::factory()->create();

    $url = URL::temporarySignedRoute('parts.download', now()->addDay(), ['part' => $part->id]);

    $this->get($url)->assertRedirect('/auth/redirect');
});

it('returns 403 for expired signed URL', function () {
    $user = User::factory()->create();
    $part = Part::factory()->create();

    Storage::disk('sheets')->put($part->file_path, 'pdf-content');

    $url = URL::temporarySignedRoute('parts.download', now()->subMinute(), ['part' => $part->id]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get($url)
        ->assertForbidden();
});

it('creates a download log entry on download', function () {
    $user = User::factory()->create();
    Permission::findOrCreate('download-all partijen');
    $user->givePermissionTo('download-all partijen');
    $piece = Piece::factory()->create();
    $part = Part::factory()->create(['piece_id' => $piece->id]);

    Storage::disk('sheets')->put($part->file_path, 'pdf-content');

    $url = URL::temporarySignedRoute('parts.download', now()->addDay(), ['part' => $part->id]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get($url)
        ->assertOk();

    expect(DownloadLog::count())->toBe(1);

    $log = DownloadLog::first();
    expect($log->user_id)->toBe($user->id);
    expect($log->part_id)->toBe($part->id);
    expect($log->downloaded_at)->not->toBeNull();
});

it('allows all editor roles to download any part', function (string $role) {
    $user = User::factory()->create();
    Permission::findOrCreate('download-all partijen');
    $user->givePermissionTo('download-all partijen');
    $piece = Piece::factory()->create();
    $part = Part::factory()->create(['piece_id' => $piece->id, 'is_conductor' => false]);

    Storage::disk('sheets')->put($part->file_path, 'pdf-content');

    $url = URL::temporarySignedRoute('parts.download', now()->addDay(), ['part' => $part->id]);

    $this->actingAs($user)
        ->withSession(['roles' => [$role]])
        ->get($url)
        ->assertOk();
})->with(['admin', 'muziekbeheer']);

it('denies member with matching instrument but wrong orchestra from downloading', function () {
    $user = User::factory()->create();
    Permission::findOrCreate('download-assigned partijen');
    $user->givePermissionTo('download-assigned partijen');
    $pieceOrchestra = Orchestra::factory()->create();
    $memberOrchestra = Orchestra::factory()->create();
    $instrumentType = InstrumentType::factory()->create();

    $piece = Piece::factory()->create();
    $piece->speelperiodes()->create(['van' => now(), 'orchestra_id' => $pieceOrchestra->id]);
    $part = Part::factory()->create([
        'piece_id' => $piece->id,
        'instrument_type_id' => $instrumentType->id,
    ]);

    Storage::disk('sheets')->put($part->file_path, 'pdf-content');

    $url = URL::temporarySignedRoute('parts.download', now()->addDay(), ['part' => $part->id]);

    $session = [
        'roles' => ['member'],
        'resolved_assignments' => [
            ['orchestra_id' => $memberOrchestra->id, 'instrument_type_id' => $instrumentType->id],
        ],
    ];

    $this->actingAs($user)
        ->withSession($session)
        ->get($url)
        ->assertForbidden();
});

it('denies member with matching orchestra but wrong instrument from downloading', function () {
    $user = User::factory()->create();
    Permission::findOrCreate('download-assigned partijen');
    $user->givePermissionTo('download-assigned partijen');
    $orchestra = Orchestra::factory()->create();
    $partInstrument = InstrumentType::factory()->create();
    $memberInstrument = InstrumentType::factory()->create();

    $piece = Piece::factory()->create();
    $piece->speelperiodes()->create(['van' => now(), 'orchestra_id' => $orchestra->id]);
    $part = Part::factory()->create([
        'piece_id' => $piece->id,
        'instrument_type_id' => $partInstrument->id,
    ]);

    Storage::disk('sheets')->put($part->file_path, 'pdf-content');

    $url = URL::temporarySignedRoute('parts.download', now()->addDay(), ['part' => $part->id]);

    $session = [
        'roles' => ['member'],
        'resolved_assignments' => [
            ['orchestra_id' => $orchestra->id, 'instrument_type_id' => $memberInstrument->id],
        ],
    ];

    $this->actingAs($user)
        ->withSession($session)
        ->get($url)
        ->assertForbidden();
});

it('returns 403 for unsigned download URL', function () {
    $user = User::factory()->create();
    $part = Part::factory()->create();

    Storage::disk('sheets')->put($part->file_path, 'pdf-content');

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get("/parts/{$part->id}/download")
        ->assertForbidden();
});

it('returns 403 for tampered signed download URL', function () {
    $user = User::factory()->create();
    $part = Part::factory()->create();

    Storage::disk('sheets')->put($part->file_path, 'pdf-content');

    $url = URL::temporarySignedRoute('parts.download', now()->addDay(), ['part' => $part->id]);
    // Tamper with the signature
    $url .= 'tampered';

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get($url)
        ->assertForbidden();
});

it('download filename uses piece title and instrument type name', function () {
    $user = User::factory()->create();
    Permission::findOrCreate('download-all partijen');
    $user->givePermissionTo('download-all partijen');
    $instrumentType = InstrumentType::factory()->create(['name' => 'Klarinet Bes']);
    $piece = Piece::factory()->create(['title' => 'Bohemian Rhapsody']);
    $part = Part::factory()->create([
        'piece_id' => $piece->id,
        'instrument_type_id' => $instrumentType->id,
    ]);

    Storage::disk('sheets')->put($part->file_path, 'pdf-content');

    $url = URL::temporarySignedRoute('parts.download', now()->addDay(), ['part' => $part->id]);

    $response = $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get($url);

    $response->assertOk();
    $response->assertDownload('bohemian-rhapsody-klarinet-bes.pdf');
});

it('multiple downloads create multiple log entries', function () {
    $user = User::factory()->create();
    Permission::findOrCreate('download-all partijen');
    $user->givePermissionTo('download-all partijen');
    $piece = Piece::factory()->create();
    $part = Part::factory()->create(['piece_id' => $piece->id]);

    Storage::disk('sheets')->put($part->file_path, 'pdf-content');

    $url = URL::temporarySignedRoute('parts.download', now()->addDay(), ['part' => $part->id]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get($url)
        ->assertOk();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get($url)
        ->assertOk();

    expect(DownloadLog::count())->toBe(2);
});

it('download log records hashed IP address', function () {
    $user = User::factory()->create();
    Permission::findOrCreate('download-all partijen');
    $user->givePermissionTo('download-all partijen');
    $piece = Piece::factory()->create();
    $part = Part::factory()->create(['piece_id' => $piece->id]);

    Storage::disk('sheets')->put($part->file_path, 'pdf-content');

    $url = URL::temporarySignedRoute('parts.download', now()->addDay(), ['part' => $part->id]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get($url)
        ->assertOk();

    $log = DownloadLog::first();
    expect($log->ip_hash)->not->toBeNull()
        ->and($log->ip_hash)->toHaveLength(64);
});

it('denies member without any roles on download route', function () {
    $user = User::factory()->create(['oidc_roles' => []]);
    $part = Part::factory()->create();

    $url = URL::temporarySignedRoute('parts.download', now()->addDay(), ['part' => $part->id]);

    $this->actingAs($user)
        ->get($url)
        ->assertForbidden();
});

it('denies member with only non-qualifying roles on download route', function () {
    $user = User::factory()->create();
    $part = Part::factory()->create();

    $url = URL::temporarySignedRoute('parts.download', now()->addDay(), ['part' => $part->id]);

    $this->actingAs($user)
        ->withSession(['roles' => ['vrijwilliger']])
        ->get($url)
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Voice field — upload, update, download filename
// ---------------------------------------------------------------------------

it('uploads a part with voice', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();
    $instrumentType = InstrumentType::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->post("/muziekstukken/{$piece->id}/parts", [
            'parts' => [
                [
                    'file' => UploadedFile::fake()->create('trompet-1.pdf', 100, 'application/pdf'),
                    'instrument_type_id' => $instrumentType->id,
                    'is_conductor' => false,
                    'voice' => 1,
                ],
            ],
        ])
        ->assertRedirect();

    $part = $piece->parts()->first();
    expect($part->voice)->toBe(1);
});

it('uploads a part without voice defaults to null', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();
    $instrumentType = InstrumentType::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->post("/muziekstukken/{$piece->id}/parts", [
            'parts' => [
                [
                    'file' => UploadedFile::fake()->create('tuba.pdf', 100, 'application/pdf'),
                    'instrument_type_id' => $instrumentType->id,
                    'is_conductor' => false,
                ],
            ],
        ])
        ->assertRedirect();

    $part = $piece->parts()->first();
    expect($part->voice)->toBeNull();
});

it('rejects voice of zero', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();
    $instrumentType = InstrumentType::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->post("/muziekstukken/{$piece->id}/parts", [
            'parts' => [
                [
                    'file' => UploadedFile::fake()->create('test.pdf', 100, 'application/pdf'),
                    'instrument_type_id' => $instrumentType->id,
                    'voice' => 0,
                ],
            ],
        ])
        ->assertSessionHasErrors('parts.0.voice');
});

it('rejects negative voice', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();
    $instrumentType = InstrumentType::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->post("/muziekstukken/{$piece->id}/parts", [
            'parts' => [
                [
                    'file' => UploadedFile::fake()->create('test.pdf', 100, 'application/pdf'),
                    'instrument_type_id' => $instrumentType->id,
                    'voice' => -1,
                ],
            ],
        ])
        ->assertSessionHasErrors('parts.0.voice');
});

it('updates part voice from null to 2', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();
    $part = Part::factory()->create([
        'piece_id' => $piece->id,
        'voice' => null,
    ]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->put("/muziekstukken/{$piece->id}/parts/{$part->id}", [
            'instrument_type_id' => $part->instrument_type_id,
            'is_conductor' => false,
            'voice' => 2,
        ])
        ->assertRedirect();

    $part->refresh();
    expect($part->voice)->toBe(2);
});

it('updates part voice from 2 to null', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();
    $part = Part::factory()->create([
        'piece_id' => $piece->id,
        'voice' => 2,
    ]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->put("/muziekstukken/{$piece->id}/parts/{$part->id}", [
            'instrument_type_id' => $part->instrument_type_id,
            'is_conductor' => false,
            'voice' => null,
        ])
        ->assertRedirect();

    $part->refresh();
    expect($part->voice)->toBeNull();
});

it('rejects voice of zero on update', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();
    $part = Part::factory()->create(['piece_id' => $piece->id]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->put("/muziekstukken/{$piece->id}/parts/{$part->id}", [
            'instrument_type_id' => $part->instrument_type_id,
            'is_conductor' => false,
            'voice' => 0,
        ])
        ->assertSessionHasErrors('voice');
});

it('download filename includes voice when set', function () {
    $user = User::factory()->create();
    Permission::findOrCreate('download-all partijen');
    $user->givePermissionTo('download-all partijen');
    $instrumentType = InstrumentType::factory()->create(['name' => 'Trompet']);
    $piece = Piece::factory()->create(['title' => 'Bohemian Rhapsody']);
    $part = Part::factory()->create([
        'piece_id' => $piece->id,
        'instrument_type_id' => $instrumentType->id,
        'voice' => 1,
    ]);

    Storage::disk('sheets')->put($part->file_path, 'pdf-content');

    $url = URL::temporarySignedRoute('parts.download', now()->addDay(), ['part' => $part->id]);

    $response = $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get($url);

    $response->assertOk();
    $response->assertDownload('bohemian-rhapsody-trompet-1.pdf');
});

it('download filename omits voice when null', function () {
    $user = User::factory()->create();
    Permission::findOrCreate('download-all partijen');
    $user->givePermissionTo('download-all partijen');
    $instrumentType = InstrumentType::factory()->create(['name' => 'Tuba']);
    $piece = Piece::factory()->create(['title' => 'Bohemian Rhapsody']);
    $part = Part::factory()->create([
        'piece_id' => $piece->id,
        'instrument_type_id' => $instrumentType->id,
        'voice' => null,
    ]);

    Storage::disk('sheets')->put($part->file_path, 'pdf-content');

    $url = URL::temporarySignedRoute('parts.download', now()->addDay(), ['part' => $part->id]);

    $response = $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get($url);

    $response->assertOk();
    $response->assertDownload('bohemian-rhapsody-tuba.pdf');
});

// ---------------------------------------------------------------------------
// Fileless parts — download/view guards
// ---------------------------------------------------------------------------

it('returns 404 when downloading a fileless part', function () {
    $user = User::factory()->create();
    Permission::findOrCreate('download-all partijen');
    $user->givePermissionTo('download-all partijen');
    $piece = Piece::factory()->create();
    $part = Part::factory()->fileless()->create(['piece_id' => $piece->id]);

    $url = URL::temporarySignedRoute('parts.download', now()->addDay(), ['part' => $part->id]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get($url)
        ->assertNotFound();
});

it('returns 404 when viewing a fileless part', function () {
    $user = User::factory()->create();
    Permission::findOrCreate('download-all partijen');
    $user->givePermissionTo('download-all partijen');
    $piece = Piece::factory()->create();
    $part = Part::factory()->fileless()->create(['piece_id' => $piece->id]);

    $url = URL::temporarySignedRoute('parts.view', now()->addDay(), ['part' => $part->id]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get($url)
        ->assertNotFound();
});

it('returns 404 when requesting download URL for a fileless part', function () {
    $user = User::factory()->create();
    Permission::findOrCreate('download-all partijen');
    $user->givePermissionTo('download-all partijen');
    $piece = Piece::factory()->create();
    $part = Part::factory()->fileless()->create(['piece_id' => $piece->id]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get("/parts/{$part->id}/download-url")
        ->assertNotFound();
});

it('returns 404 when requesting view URL for a fileless part', function () {
    $user = User::factory()->create();
    Permission::findOrCreate('download-all partijen');
    $user->givePermissionTo('download-all partijen');
    $piece = Piece::factory()->create();
    $part = Part::factory()->fileless()->create(['piece_id' => $piece->id]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->get("/parts/{$part->id}/view-url")
        ->assertNotFound();
});

it('deletes a fileless part without storage errors', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();
    $part = Part::factory()->fileless()->create(['piece_id' => $piece->id]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->delete("/muziekstukken/{$piece->id}/parts/{$part->id}")
        ->assertRedirect();

    expect(Part::find($part->id))->toBeNull();
});

// ---------------------------------------------------------------------------
// Delete all parts — admin-only bulk delete
// ---------------------------------------------------------------------------

it('deletes all parts and removes files from disk', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();
    $instrumentType = InstrumentType::factory()->create();

    $part1 = Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $instrumentType->id]);
    $part2 = Part::factory()->create(['piece_id' => $piece->id, 'instrument_type_id' => $instrumentType->id]);

    Storage::disk('sheets')->put($part1->file_path, 'pdf-content');
    Storage::disk('sheets')->put($part2->file_path, 'pdf-content');

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->delete("/muziekstukken/{$piece->id}/parts")
        ->assertRedirect();

    expect(Part::where('piece_id', $piece->id)->count())->toBe(0);
    Storage::disk('sheets')->assertMissing($part1->file_path);
    Storage::disk('sheets')->assertMissing($part2->file_path);
});

it('delete all parts handles mixed file and fileless parts', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();

    $filePart = Part::factory()->create(['piece_id' => $piece->id]);
    $filelessPart = Part::factory()->fileless()->create(['piece_id' => $piece->id]);

    Storage::disk('sheets')->put($filePart->file_path, 'pdf-content');

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->delete("/muziekstukken/{$piece->id}/parts")
        ->assertRedirect();

    expect(Part::where('piece_id', $piece->id)->count())->toBe(0);
    Storage::disk('sheets')->assertMissing($filePart->file_path);
});

it('delete all parts succeeds on piece with no parts', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->delete("/muziekstukken/{$piece->id}/parts")
        ->assertRedirect();

    expect(Part::where('piece_id', $piece->id)->count())->toBe(0);
});

it('delete all parts does not affect parts of other pieces', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();
    $otherPiece = Piece::factory()->create();
    $otherPart = Part::factory()->create(['piece_id' => $otherPiece->id]);

    Part::factory()->create(['piece_id' => $piece->id]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->delete("/muziekstukken/{$piece->id}/parts")
        ->assertRedirect();

    expect(Part::find($otherPart->id))->not->toBeNull();
});

it('delete all parts preserves the piece itself', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();
    Part::factory()->count(3)->create(['piece_id' => $piece->id]);

    $this->actingAs($user)
        ->withSession(['roles' => ['admin']])
        ->delete("/muziekstukken/{$piece->id}/parts")
        ->assertRedirect();

    expect(Piece::find($piece->id))->not->toBeNull();
    expect(Part::where('piece_id', $piece->id)->count())->toBe(0);
});

it('redirects guests from delete all parts', function () {
    $piece = Piece::factory()->create();
    Part::factory()->create(['piece_id' => $piece->id]);

    $this->delete("/muziekstukken/{$piece->id}/parts")
        ->assertRedirect('/auth/redirect');

    expect($piece->parts()->count())->toBe(1);
});

it('denies non-admin editor from deleting all parts', function () {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();
    $part = Part::factory()->create(['piece_id' => $piece->id]);

    $this->actingAs($user)
        ->withSession(['roles' => ['muziekbeheer']])
        ->delete("/muziekstukken/{$piece->id}/parts")
        ->assertForbidden();

    expect(Part::find($part->id))->not->toBeNull();
});

it('denies non-admin roles from deleting all parts', function (string $role) {
    $user = User::factory()->create();
    $piece = Piece::factory()->create();
    $part = Part::factory()->create(['piece_id' => $piece->id]);

    $this->actingAs($user)
        ->withSession(['roles' => [$role]])
        ->delete("/muziekstukken/{$piece->id}/parts")
        ->assertForbidden();

    expect(Part::find($part->id))->not->toBeNull();
})->with(['member', 'lid', 'dirigent', 'vrijwilliger']);

it('denies users with no roles from deleting all parts', function () {
    $user = User::factory()->create(['oidc_roles' => []]);
    $piece = Piece::factory()->create();
    $part = Part::factory()->create(['piece_id' => $piece->id]);

    $this->actingAs($user)
        ->withSession(['roles' => []])
        ->delete("/muziekstukken/{$piece->id}/parts")
        ->assertForbidden();

    expect(Part::find($part->id))->not->toBeNull();
});
