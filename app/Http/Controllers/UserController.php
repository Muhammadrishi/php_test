<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Mail\AccountCreated;
use App\Mail\NewUserNotification;

class UserController extends Controller
{
    // Im using the package we should have to login for to check the can_edit method
    
    // new user creation
    public function create(Request $request)
    {
        // validation rules
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required',  'min:8'],
        ]);

        // If validation fails, return errors in the postmanapi response
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Retrieve validated data
        $validated = $validator->validated();

        // Create new user with validated data
        $user = User::create([
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']), // Encrypt password
            'name' => $validated['name'],
        ]);

        //  creation email to the user
        Mail::to($user->email)->send(new AccountCreated($user));

        //  notification email to the administrator
        Mail::to('admin@example.com') //  you can replace with your admin email
            ->send(new NewUserNotification($user));

        // Return a JSON response with the user details ~exclude password~
        return response()->json([
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'created_at' => $user->created_at->toIso8601String(),
        ], 201); // 201 Created status code
    }
    public function index(Request $request)
    {
        $query = User::query()->where('active', true);
        // Apply search filter (optional)
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%");
            });
        }
        // Apply sorting by valid columns (name, email, created_at)
        if ($sortBy = $request->get('sortBy')) {
            if (in_array($sortBy, ['name', 'email', 'created_at'])) {
                $query->orderBy($sortBy, 'asc');
            }
        } else {
            $query->orderBy('created_at', 'asc'); // Default sorting
        }
        // Paginate results
        $users = $query->paginate(10);
        // Add orders_count to each user and format data
        $users->getCollection()->transform(function ($user) {
            $user->orders_count = $user->orders()->count();
            $user->can_edit = $this->canEditUser($user); // Logic for editing permissions
            $user->created_at = $user->created_at->toIso8601String(); // ISO format
            return $user;
        });
        return response()->json([
            'page' => $users->currentPage(),
            'users' => $users->items(),
        ]);
    }
    

//    Determine the specific user
    private function canEditUser(User $user)
    {
        $currentUser = auth()->user(); 
        // Authenticated user

        // If the user is not authenticated, return false or handle appropriately
        if (!$currentUser) {
            return false;  // Or throw an exception based on your app needs
        }
        //AS  Administrator can edit any user
        if ($currentUser->role === 'administrator') {
            return true;
        }
        //AS Manager can only edit users with the 'user' role
        if ($currentUser->role === 'manager' && $user->role === 'user') {
            return true;
        }
        //AS Regular users can only edit themselves
        return $currentUser->id === $user->id;
    }
}
