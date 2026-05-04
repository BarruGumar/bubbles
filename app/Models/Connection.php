<<<<<<< HEAD
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Connection extends Model
{
    use HasFactory;

=======
class Connection extends Model
{
>>>>>>> da5b16d (merda)
    protected $fillable = [
        'from_bubble_id',
        'to_bubble_id',
    ];

<<<<<<< HEAD
    public function fromBubble(): BelongsTo
=======
    public function from()
>>>>>>> da5b16d (merda)
    {
        return $this->belongsTo(Bubble::class, 'from_bubble_id');
    }

<<<<<<< HEAD
    public function toBubble(): BelongsTo
    {
        return $this->belongsTo(Bubble::class, 'to_bubble_id');
    }
}
=======
    public function to()
    {
        return $this->belongsTo(Bubble::class, 'to_bubble_id');
    }
}
>>>>>>> da5b16d (merda)
