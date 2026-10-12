<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AccessManagement;
use App\Support\DisplayDates;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function directory()
    {
        return view('users', ['users' => User::orderBy('last_name')->orderBy('first_name')->orderBy('id')->get()]);
    }

    public function show(User $user)
    {
        return view('user-show', ['user' => $user]);
    }

    private function profileUser(Request $request): User
    {
        $target = $request->query('user');
        $user = $request->user();
        if ($target !== null) {
            $data = validator(['user' => $target], ['user' => ['required', 'integer', 'min:1']])->validate();
            $user = User::findOrFail($data['user']);
        }
        abort_unless($request->user()->enabled && ((int) $request->user()->id === (int) $user->id || $request->user()->hasRole('admin')), 403);

        return $user;
    }

    public function profile(Request $request)
    {
        return view('profile', ['profileUser' => $this->profileUser($request)]);
    }

    public function saveProfile(Request $request, AccessManagement $access)
    {
        $profileUser = $this->profileUser($request);
        $input = $request->only(['first_name', 'last_name', 'contact_email', 'contact_attested', 'time_zone',
            'personal_email', 'mobile_number', 'mailing_address', 'display_density']);
        // Reject controls before global normalization could hide invalid input.
        foreach (['first_name', 'last_name'] as $name) {
            if (is_string($input[$name] ?? null) && preg_match('/\p{Cc}/u', $input[$name]) === 1) {
                throw ValidationException::withMessages([$name => 'Names cannot contain control characters.']);
            }
        }
        validator($input, [
            'first_name' => ['nullable', 'string', 'not_regex:/\p{Cc}/u'],
            'last_name' => ['nullable', 'string', 'not_regex:/\p{Cc}/u'],
        ])->validate();
        foreach (['first_name', 'last_name'] as $name) {
            $value = $input[$name] ?? '';
            $input[$name] = is_string($value) ? preg_replace('/^\s+|\s+$/u', '', $value) : $value;
        }
        $input['contact_email'] = is_string($input['contact_email'] ?? null) ? trim($input['contact_email']) : ($input['contact_email'] ?? '');
        $data = validator($input, [
            'first_name' => ['required', 'string', 'max:100', 'not_regex:/\p{Cc}/u'],
            'last_name' => ['required', 'string', 'max:100', 'not_regex:/\p{Cc}/u'],
            'contact_email' => ['bail', 'nullable', 'string', 'max:255', 'email:rfc', function ($attribute, $value, $fail) {
                if (strtolower(substr(strrchr($value, '@'), 1)) !== 'tabletopgaymers.org') {
                    $fail('Use an address at tabletopgaymers.org.');
                }
            }],
            'contact_attested' => ['sometimes', 'boolean'],
            'time_zone' => ['sometimes', 'required', 'string', Rule::in(DisplayDates::zones())],
            'personal_email' => ['nullable', 'string', 'max:255', 'email:rfc'],
            'mobile_number' => ['nullable', 'string', 'max:100'],
            'mailing_address' => ['nullable', 'string', 'max:4000'],
            'display_density' => ['sometimes', 'required', 'string', Rule::in(array_keys(User::DISPLAY_DENSITIES))],
        ])->validate();
        $data['first_name'] = $data['first_name'] ?? '';
        $data['last_name'] = $data['last_name'] ?? '';
        $data['contact_email'] = $data['contact_email'] ?? '';
        $data['contact_attested'] = $request->boolean('contact_attested');
        foreach (['personal_email', 'mobile_number', 'mailing_address'] as $field) {
            $data[$field] = ($data[$field] ?? '') === '' ? null : $data[$field];
        }
        if (is_string($data['mobile_number']) && preg_match('/\A[0-9]{10}\z/', $data['mobile_number'])) {
            $data['mobile_number'] = substr($data['mobile_number'], 0, 3).'-'.substr($data['mobile_number'], 3, 3).'-'.substr($data['mobile_number'], 6);
        }
        $access->saveProfile($profileUser->id, $data, $request->user()->id);

        return redirect('/profile'.((int) $profileUser->id === (int) $request->user()->id ? '' : '?user='.$profileUser->id))->with('success', 'Profile saved.');
    }

    public function role(Request $request, User $user, AccessManagement $access)
    {
        $data = $request->validate(['role' => ['required', 'string'], 'grant' => ['required', 'boolean']]);
        $access->changeRole($request->user()->id, $user->id, $data['role'], $request->boolean('grant'));

        return redirect('/users/'.$user->id)->with('success', 'Role saved.');
    }

    public function disable(Request $request, User $user, AccessManagement $access)
    {
        $access->disable($request->user()->id, $user->id);

        return redirect('/users/'.$user->id)->with('success', 'Account disabled.');
    }

    public function enable(Request $request, User $user, AccessManagement $access)
    {
        $access->enable($request->user()->id, $user->id);

        return redirect('/users/'.$user->id)->with('success', 'Account enabled.');
    }
}
