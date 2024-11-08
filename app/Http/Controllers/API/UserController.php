<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use App\Mail\OtpMail;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    protected $otpStore = []; // Store OTPs temporarily (for testing)
    // New User Register API
    public function register(Request $request)
    {
        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        // If validation fails
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], status: 400);
        }

        // Create a new user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Return success response
        return response()->json([
            'message' => 'User registered successfully!',
            'user' => $user
        ], 201);
    }

    // Get Single User By ID
    public function getUser($id)
    {
        // Find the user by ID
        $user = User::find($id);

        // If the user is not found, return an error
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Return the user data if found
        return response()->json([
            'message' => 'User found',
            'user' => $user
        ], 200);
    }

    // Update user details
    public function updateUser(Request $request, $id)
    {
        // Find the user by ID
        $user = User::find($id);

        // If the user is not found, return an error
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'sometimes|string|min:8|confirmed',
        ]);

        // If validation fails
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Update the user details if provided
        if ($request->has('name')) {
            $user->name = $request->name;
        }

        if ($request->has('email')) {
            $user->email = $request->email;
        }

        if ($request->has('password')) {
            $user->password = Hash::make($request->password);
        }

        // Save the updated user details
        $user->save();

        // Return success response
        return response()->json([
            'message' => 'User updated successfully!',
            'user' => $user
        ], 200);
    }

    // Delete a user by ID
    public function deleteUser($id)
    {
        // Find the user by ID
        $user = User::find($id);

        // If the user is not found, return an error
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Delete the user
        $user->delete();

        // Return success response
        return response()->json([
            'message' => 'User deleted successfully!'
        ], 200);
    }


    // User Login
    public function login(Request $request)
    {
        // Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // If validation fails
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Check if user exists
        $user = User::where('email', $request->email)->first();

        // If user not found or password is incorrect
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        // Create a token for the user
        $token = $user->createToken('user-token')->plainTextToken;

        // Return success response with token
        return response()->json([
            'message' => 'Login successful!',
            'user' => $user,
            'token' => $token
        ], 200);
    }

    // Forgot password API to generate OTP and send email
    public function forgotPassword(Request $request)
    {
        // Validate email input
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $user = User::where('email', $request->email)->first();

        // Generate OTP
        $otp = rand(100000, 999999);

        // Store OTP in cache with a 5-minute expiration
        Cache::put('otp_' . $user->id, ['otp' => $otp, 'created_at' => now()], now()->addMinutes(5));

        // Log the generated OTP for debugging purposes
        Log::info('Generated OTP for ' . $user->email . ': ' . $otp);

        // Send the OTP via email
        Mail::to($user->email)->send(new OtpMail($otp));

        return response()->json(['message' => 'OTP sent successfully! Please check your email.'], 200);
    }


    // Verify OTP API
    public function verifyOtp(Request $request)
    {
        // Validate OTP input
        $validator = Validator::make($request->all(), [
            'otp' => 'required|integer',
            'user_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $userId = $request->user_id;

        // Fetch OTP from cache
        $cachedOtp = Cache::get('otp_' . $userId);

        // Log the current OTP for debugging purposes
        Log::info('OTP fetched from cache: ', [$cachedOtp]);

        // Check if OTP exists and is not expired
        if (!$cachedOtp) {
            return response()->json(['error' => 'OTP not generated or has expired.'], 400);
        }

        // Check if the OTP has expired (5-minute expiration is handled by Cache, but we can still check manually)
        if (now()->diffInMinutes($cachedOtp['created_at']) > 5) {
            return response()->json(['error' => 'OTP has expired.'], 400);
        }

        // Validate the OTP
        if ($cachedOtp['otp'] !== (int)$request->otp) {
            return response()->json(['error' => 'Invalid OTP'], 400);
        }

        // Remove the OTP from cache after successful verification to prevent reuse
        Cache::forget('otp_' . $userId);

        return response()->json(['success' => 'OTP verified successfully.'], 200);
    }

    // Password Reset (After OTP Verification)
    public function resetPassword(Request $request)
    {
        // Validate password input
        $validator = Validator::make($request->all(), [
            'password' => 'required|min:6|confirmed',
            'user_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        $user = User::find($request->user_id);

        if (!$user) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        // Update the user's password
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json(['success' => 'Password reset successfully.'], 200);
    }
}
