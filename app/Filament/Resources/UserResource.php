<?php
/*
 *
 * TODO:
 * - Dude make your table migrations first looooool filament won't do that for you
 *
 * */

namespace App\Filament\Resources;

use Filament\Facades\Filament;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use App\Models\Rph;

use Filament\Forms;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Hidden;

use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?int $navigationSort = 2;

    private static function rph_selector($columns) {
        $rph_sel = Select::make('rph_id')
            ->label('RPH')
            ->native(false)
            ->options(Rph::all()->pluck('name', 'id'))
            ->default(function ($record) {
                if ($record && $record->profile && $record->profile->rph_id) {
                    return $record->profile->rph_id;
                }
                return null; // No default if no linked RPH
            })
            ->disabled(function ($get, $state) {
                return RPH::count() === 0;
            })
            ->hint(function ($state) {
                if (RPH::count() === 0) {
                    return 'Please add an RPH first.';
                }
                return null;
            });

        if ($columns) { $rph_sel = $rph_sel->columnSpan(['xl' => $columns]); }
        return $rph_sel;
    }

    public static function form(Form $form): Form
    {

        return $form
            ->schema([
                Section::make()
                    ->columns(['xl' => 6])
                    ->schema([
                        TextInput::make('name')
                            ->columnSpan(['xl' => 3])
                            ->label('Nama Lengkap')
                            ->required(),
                        TextInput::make('email')
                            ->columnSpan(['xl' => 3])
                            ->email()
                            ->required(),
                        TextInput::make('password')
                            ->columnSpan(['xl' => 3])
                            ->password()
                            ->confirmed()
                            ->required(),
                         TextInput::make('password_confirmation')
                             ->columnSpan(['xl' => 3])
                             ->password()
                             ->required()
                             ->maxLength(255)
                             ->same('password')
                             ->dehydrated(false)
                             ->label('Confirm Password'),
                         TextInput::make('phone')
                             ->columnSpan(['xl' => 3])
                             ->label('No Telp'),
                         Textarea::make('alamat')
                             ->columnSpan(['xl' => 3]),
                         Select::make('role')
                             ->columnSpan(['xl' => 3])
                             ->options(function () {
                                 $user = auth()->user();

                                 if ($user->hasRole('super_admin')) {
                                     return [
                                         'super_admin' => 'Super Admin',
                                         'admin_rph' => 'Admin RPH',
                                     ]; 
                                 }

                                 if ($user->hasRole('admin_rph')) {
                                     return [
                                         'admin_rph' => 'Admin RPH',
                                         'penyelia' => 'Penyelia',
                                         'peternak' => 'Peternak',
                                     ];
                                 }

                                 return [];
                             })
                             ->native(false)
                             ->required()
                             ->live(),
                    ]),

                    Fieldset::make('Role')
                        ->schema([])
                        ->visible(fn (
                            Forms\Get $get
                        ) => $get('role') == ''),

                    Fieldset::make('Super Admin')
                        ->relationship('profile')
                        ->schema([
                            Textarea::make('notes')
                            ->label('Catatan')
                        ])
                        ->visible(fn (
                            Forms\Get $get
                        ) => $get('role') == 'super_admin'),

                    Fieldset::make('Admin RPH')
                        ->relationship('profile')
                        ->schema([
                            Select::make('rph_id')
                                ->label('RPH')
                                ->native(false)
                                ->options(Rph::all()->pluck('name', 'id'))
                                ->default(function ($record) {
                                    if ($record && $record->profile && $record->profile->rph_id) {
                                        return $record->profile->rph_id;
                                    }
                                    return null; // No default if no linked RPH
                                })
                                ->disabled(function ($get, $state) {
                                    return RPH::count() === 0;
                                })
                                ->hint(function ($state) {
                                    if (RPH::count() === 0) {
                                        return 'Please add an RPH first.';
                                    }
                                    return null;
                                })
                        ])
                        ->visible(fn (
                            Forms\Get $get
                        ) => $get('role') == 'admin_rph'),

                    Fieldset::make('Juleha')
                        ->relationship('profile')
                        ->schema([
                         TextInput::make('nomor_sertifikat')
                             ->columnSpan(['xl' => 3])
                             ->label('Nomor Sertifikat'),
                         TextInput::make('masa_sertifikat')
                             ->columnSpan(['xl' => 3])
                             ->label('Masa Berlaku'),
                         TextInput::make('upload_sertifikat')
                             ->columnSpan(['xl' => 3])
                             ->label('Upload Sertifikat'),
                        ])
                        ->visible(fn (
                            Forms\Get $get
                        ) => $get('role') === 'juleha'),

                    Fieldset::make('Penyelia')
                        ->columns(['xl' => 2])
                        ->relationship('profile')
                        ->schema([
                         TextInput::make('nip')
                             ->columnSpan(['xl' => 1])
                             ->label('Nomor Induk Penyelia'),
                         TextInput::make('status')
                             ->columnSpan(['xl' => 1]),
                         DatePicker::make('tgl_berlaku')
                             ->columnSpan(['xl' => 1])
                             ->native(false)
                             ->label('Tanggal Berlaku'),
                         FileUpload::make('file_sk')
                             ->columnSpan(['xl' => 1])
                             ->label('Upload SK'),
                        ])
                        ->visible(fn (
                            Forms\Get $get
                        ) => $get('role') === 'penyelia'),

                    Fieldset::make('Peternak')
                        ->columns(['xl' => 2])
                        ->relationship('profile')
                        ->schema([
                            Select::make('status_usaha')
                                ->label('Status Usaha')
                                ->columnSpan(['xl' => 2])
                                ->native(false)
                                ->options([
                                    'Belum Terdaftar' => 'Belum Terdaftar',
                                    'Terdaftar' => 'Terdaftar'
                                ])
                        ])
                        ->visible(fn (
                            Forms\Get $get
                        ) => $get('role') === 'peternak'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('role')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    /*
     * Role based query builder. Filter based on role
     * */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        return parent::getEloquentQuery()
            ->when($user->hasRole('super_admin'), function ($query) {
                return $query->whereIn('role', ['super_admin', 'admin_rph']);
            })
            ->when($user->hasRole('admin_rph'), function ($query) use ($user) {
                $rph_id = $user->profile?->rph_id;

                // Return the same query but use subquery instead of where
                return $query->where(function ($query) use ($rph_id) {
                  $query->whereIn('id', function ($subquery) use ($rph_id) {
                      $subquery->select('user_id')
                          ->from('admin_rph') 
                          ->where('rph_id', $rph_id);
                  })->orWhereIn('id', function ($subquery) use ($rph_id) {
                      $subquery->select('user_id')
                          ->from('penyelia')
                          ->where('rph_id', $rph_id);
                  });
                });
            })
            ;
    }
}
