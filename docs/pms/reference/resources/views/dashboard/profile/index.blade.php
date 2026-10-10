@extends('layouts.app')
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 col-12 mb-4">
                                <label for="name" class="form-label">Name</label>
                                <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" class="form-control" placeholder="Enter Name">
                                @error('name') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-md-6 col-12 mb-4">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" id="email" value="{{ $user->email }}" class="form-control bg-light" readonly>
                            </div>
                            <div class="col-6 mb-4">
                                <label for="profile_img" class="form-label">Update Profile Image</label>
                                <div class="form-text mb-1"><i class="fa fa-circle-info me-1"></i>JPG, JPEG, PNG, GIF or SVG · Maximum 2 MB.</div>
                                <input type="file" id="profile_img" name="profile_img" class="form-control" accept=".jpg,.jpeg,.png,.gif,.svg">
                                @error('profile_img') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-6 mb-4">
                                <img src="{{ $user->profile_img }}" alt="{{ $user->name }}" width="100" height="100">
                            </div>
                            <span class="text-muted">Change password if you want</span>
                            <div class="col-md-6 col-12 mb-4">
                                <label for="current-password" class="form-label">Current Password</label>
                                <input type="password" name="current-password" id="current-password" class="form-control" placeholder="Current Password">
                                @error('current-password') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-md-6 col-12 mb-4">
                                <label for="new-password" class="form-label">New Password</label>
                                <input type="password" name="new-password" id="new-password" class="form-control" placeholder="New Password">
                                @error('new-password') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-md-6 col-12 mb-4">
                                <label for="repeat-password" class="form-label">Repeat Password</label>
                                <input type="password" name="repeat-password" id="repeat-password" class="form-control" placeholder="Repeat Password">
                                @error('repeat-password') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
@endsection
