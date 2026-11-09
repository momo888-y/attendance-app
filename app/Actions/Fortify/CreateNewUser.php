<?php

namespace App\Actions\Fortify;

use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    /**
     * 会員登録の入力をチェックし、問題なければ新しいユーザーを作成する
     * （チェック内容とメッセージは RegisterRequest のものを使い回す）
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        $request = new RegisterRequest();

        Validator::make($input, $request->rules(), $request->messages())->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);
    }
}
