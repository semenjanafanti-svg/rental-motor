<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetRequest;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()
            ->where('email', $request->string('email')->lower()->toString())
            ->where('role', 'customer')
            ->first();

        if ($user) {
            PasswordResetRequest::updateOrCreate(
                ['user_id' => $user->id],
                ['status' => 'pending', 'requested_at' => now(), 'processed_by' => null, 'processed_at' => null],
            );

            $owners = User::query()->where('role', 'super_admin')->where('is_active', true)->get();
            Notification::make()
                ->title('Permintaan reset password baru')
                ->body("{$user->name} meminta password baru.")
                ->warning()
                ->sendToDatabase($owners);
        }

        return back()->with('status', 'Permintaan reset password sudah dikirim ke owner. Password baru akan dikirim melalui WhatsApp setelah diproses.');
    }
}
