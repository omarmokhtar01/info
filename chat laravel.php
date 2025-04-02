composer require laravel/reverb
composer require laravel/sanctum
php artisan reverb:install

php artisan reverb:start
php artisan serve


بدل command queue:listen
QUEUE_CONNECTION=sync

php artisan queue:listen

   INFO  Processing jobs from the [default] queue.  


   php artisan reverb:start


npm install --save-dev laravel-echo pusher-js
npm run build

/////////////////////////////

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user1_id')->constrained('users');
    $table->foreignId('user2_id')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chats');
    }
};


////////////////////////////////////

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users');
            $table->text('message')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
///////////////////////////////



<?php
// app/Events/NewMessage.php
namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    public function broadcastOn()
    {
        return new PresenceChannel('chat.'.$this->message->chat_id);
    }

    public function broadcastAs()
    {
        return 'new-message';
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->message->id,
            'chat_id' => $this->message->chat_id,
            'sender_id' => $this->message->sender_id,
            'message' => $this->message->message,
            'file_path' => $this->message->file_path,
            'file_name' => $this->message->file_name,
            'file_type' => $this->message->file_type,
            'file_size' => $this->message->file_size,
            'created_at' => $this->message->created_at->toDateTimeString(),
            'sender' => [
                'id' => $this->message->sender->id,
                'name' => $this->message->sender->name,
            ]
        ];
    }
}

/////////////////////////////////////////////////////////




<?php
// app/Http/Controllers/AuthController.php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('auth_token')->plainTextToken
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('auth_token')->plainTextToken
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }
}

/////////////////////////////////////////////////////////

<?php
namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\User;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        $chats = Chat::with(['user1', 'user2', 'latestMessage'])
            ->where('user1_id', $user->id)
            ->orWhere('user2_id', $user->id)
            ->get()
            ->map(function ($chat) use ($user) {
                $chat->other_user = $chat->user1_id === $user->id ? $chat->user2 : $chat->user1;
                return $chat;
            });
    
        return response()->json($chats);
    }
    
    public function show(Request $request, $userId)
    {
        $user = $request->user();

        $chat = Chat::where(function($query) use ($user, $userId) {
            $query->where('user1_id', $user->id)
                ->where('user2_id', $userId);
        })->orWhere(function($query) use ($user, $userId) {
            $query->where('user1_id', $userId)
                ->where('user2_id', $user->id);
        })->first();

        if (!$chat) {
            $chat = Chat::firstOrCreate(
                ['user1_id' => min($user->id, $userId), 'user2_id' => max($user->id, $userId)]
            );
            
        }

        return response()->json([
            'chat' => $chat,
            'other_user' => $chat->user1_id === $user->id ? $chat->user2 : $chat->user1
        ]);
    }
}


/////////////////////////////////////////////////////////

<?php
// app/Http/Controllers/MessageController.php
namespace App\Http\Controllers;

use App\Events\NewMessage;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    public function index(Request $request, $chatId)
    {
        $messages = Message::with('sender')
            ->where('chat_id', $chatId)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($messages);
    }

    public function store(Request $request, $chatId)
    {
        $request->validate([
            'message' => 'nullable|string',
            'file' => 'nullable|file|max:10240', // 10MB max
        ]);

        $chat = Chat::findOrFail($chatId);
        $user = $request->user();

        $filePath = null;
        $fileName = null;
        $fileType = null;
        $fileSize = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filePath = $file->store('chat_files', 'public');
            $fileName = $file->getClientOriginalName();
            $fileType = $file->getClientMimeType();
            $fileSize = $file->getSize();
        }

        $message = Message::create([
            'chat_id' => $chatId,
            'sender_id' => $user->id,
            'message' => $request->message,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_type' => $fileType,
            'file_size' => $fileSize,
        ]);

        broadcast(new NewMessage($message))->toOthers();

        return response()->json($message->load('sender'), 201);
    }

    public function downloadFile($messageId)
    {
        $message = Message::findOrFail($messageId);
        
        if (!$message->file_path) {
            return response()->json(['error' => 'File not found'], 404);
        }

        $filePath = storage_path('app/public/' . $message->file_path);

        return response()->download($filePath, $message->file_name, [
            'Content-Type' => $message->file_type,
        ]);
    }
}




/////////////////////////////////////////////////////////


<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    use HasFactory;

    protected $fillable = ['user1_id', 'user2_id'];

    public function user1()
    {
        return $this->belongsTo(User::class, 'user1_id');
    }
    
    public function user2()
    {
        return $this->belongsTo(User::class, 'user2_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latest();
    }

    public function otherUser()
    {
        return $this->user1_id === auth()->id() ? User::find($this->user2_id) : User::find($this->user1_id);
    }
}



///////////////////////////////////////////


<?php


// app/Models/Message.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = ['chat_id', 'sender_id', 'message', 'file_path', 'file_name'];

    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}


////////////////////////////////////////////









<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | This option controls the default broadcaster that will be used by the
    | framework when an event needs to be broadcast. You may set this to
    | any of the connections defined in the "connections" array below.
    |
    | Supported: "reverb", "pusher", "ably", "redis", "log", "null"
    |
    */

    'default' => env('BROADCAST_CONNECTION', 'reverb'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the broadcast connections that will be used
    | to broadcast events to other systems or over WebSockets. Samples of
    | each available type of connection are provided inside this array.
    |
    */

    'connections' => [

        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host' => env('REVERB_HOST'),
                'port' => env('REVERB_PORT', 8080),
                'scheme' => env('REVERB_SCHEME', 'https'),
                'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
            ],
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
        ],

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'host' => env('PUSHER_HOST') ?: 'api-'.env('PUSHER_APP_CLUSTER', 'mt1').'.pusher.com',
                'port' => env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
            ],
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
        ],

        'ably' => [
            'driver' => 'ably',
            'key' => env('ABLY_KEY'),
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];









//////////////////////////////////////////
<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\MessageController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    
    Route::get('/chats', [ChatController::class, 'index']);
    Route::get('/chats/{userId}', [ChatController::class, 'show']);
    
    Route::get('/messages/{chatId}', [MessageController::class, 'index']);
    Route::post('/messages/{chatId}', [MessageController::class, 'store']);
    Route::get('/messages/{messageId}/download', [MessageController::class, 'downloadFile']);
});