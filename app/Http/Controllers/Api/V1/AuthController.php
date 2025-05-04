<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Facades\Route;

class AuthController extends Controller
{
    // Phương thức register (đã có)
    public function register(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'username' => 'required|string|max:255|unique:users',
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }

            $user = User::create([
                'username' => $request->username,
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            $token = $user->createToken('MyApp')->accessToken;

            return response()->json(['token' => $token], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // Phương thức login (mới)
    public function login(Request $request)
    {
        try {
            // Xác thực dữ liệu đầu vào
            $validator = Validator::make($request->all(), [
                'username' => 'required|string',
                'password' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }

            // Tìm người dùng theo username
            $user = User::where('username', $request->username)->first();

            // Kiểm tra người dùng tồn tại và mật khẩu đúng
            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json(['error' => 'Invalid credentials'], 401);
            }

            // Tạo token cho người dùng
            $token = $user->createToken('MyApp')->accessToken;

            return response()->json(['token' => $token], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    // Phương thức profile (mới)
    public function profile(Request $request)
    {
        try {
            // Lấy người dùng hiện tại từ token
            $user = auth()->user();

            // Trả về thông tin người dùng
            return response()->json($user, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateAvatar(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'avatar' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }

            $user = auth()->user();

            if ($request->hasFile('avatar')) {
                if ($user->avatar) {
                    Storage::disk('public')->delete($user->avatar);
                }

                $avatarPath = $request->file('avatar')->store('avatars', 'public');
                $user->avatar = $avatarPath;
                $user->save();

                $avatarUrl = Storage::url($avatarPath);

                return response()->json([
                    'message' => 'Avatar updated successfully.',
                    'avatar_url' => $avatarUrl,
                ], 200);
            }

            return response()->json(['error' => 'No avatar file uploaded.'], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function updateProfile(Request $request)
    {
        try {
            // Xác thực dữ liệu đầu vào
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email,' . auth()->id(),
                'phone' => 'nullable|string|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }

            // Lấy người dùng hiện tại
            $user = auth()->user();

            // Cập nhật thông tin
            $user->name = $request->name;
            $user->email = $request->email;
            $user->phone = $request->phone;
            $user->save();

            // Tạo URL avatar nếu có
            $userData = $user->toArray();
            if ($user->avatar) {
                $userData['avatar_url'] = url('/storage/' . $user->avatar);
            }

            return response()->json([
                'message' => 'Profile updated successfully.',
                'user' => $userData,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function deleteAccount(Request $request)
    {
        try {
            // Lấy người dùng hiện tại
            $user = auth()->user();

            // Xóa avatar nếu có
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }

            // Xóa token của người dùng (Passport)
            $user->tokens()->delete();

            // Xóa tài khoản người dùng
            $user->delete();

            return response()->json([
                'message' => 'Account deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function searchUser(Request $request)
    {
        try {
            // Xác thực tham số đầu vào
            $validator = Validator::make($request->query(), [
                'keyword' => 'required|string|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }

            // Lấy từ khóa tìm kiếm
            $keyword = $request->query('keyword');

            // Tìm kiếm người dùng theo username hoặc email
            $users = User::where('username', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%")
                        ->get();

            // Nếu không tìm thấy người dùng
            if ($users->isEmpty()) {
                return response()->json(['message' => 'No users found.'], 404);
            }

            // Thêm avatar_url vào kết quả
            $usersData = $users->map(function ($user) {
                $userData = $user->toArray();
                $userData['avatar_url'] = $user->avatar ? url('/storage/' . $user->avatar) : null;
                return $userData;
            });

            return response()->json([
                'user' => $usersData,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to search users.',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}