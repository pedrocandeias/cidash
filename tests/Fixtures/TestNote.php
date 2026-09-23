<?php

namespace Tests\Fixtures;

use App\Core\Concerns\IsRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A minimal domain model used to test the core without depending on a module.
 *
 * @property string $id
 * @property string $title
 * @property string|null $body
 */
#[Fillable(['title', 'body'])]
class TestNote extends Model
{
    use IsRecord;

    protected $table = 'test_notes';

    public static function setUpTable(): void
    {
        Schema::create('test_notes', function (Blueprint $table) {
            $table->foreignUuid('id')->primary()->constrained('objects')->cascadeOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->timestamps();
        });

        Relation::morphMap(['test_note' => self::class]);
    }
}
