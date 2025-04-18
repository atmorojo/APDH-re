<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TernakResource\Pages;
use App\Filament\Resources\TernakResource\RelationManagers;
use App\Models\Ternak;
use App\Models\Peternak;
use App\Models\Juleha;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Carbon\Carbon;

class TernakResource extends Resource
{
    protected static ?string $model = Ternak::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        $peternaks = Peternak::with('user')->get();
        return $form
            ->schema([
                Forms\Components\TextInput::make('bobot')
                    ->label('Bobot')
                    ->type('number')
                    ->step('0.01')
                    ->minValue('0')
                    ->required(),
                Forms\Components\Select::make('jenis')
                    ->label('Jenis')
                    ->options([
                        'Kambing' => 'Kambing',
                        'Sapi' => 'Sapi',
                        'Kerbau' => 'Kerbau',
                    ])
                    ->native(false)
                    ->required(),
                Forms\Components\Select::make('peternak_id')
                    ->label('Peternak')
                    ->native(false)
                    ->options(
                        $peternaks->mapWithKeys(function ($peternak) {
                            return $peternak->user ? [
                                $peternak->user->id => $peternak->user->name,
                            ] : null;
                        })
                    )
                    ->disabled(function ($get, $state) use ($peternaks) {
                        return $peternaks->count() === 0;
                    })
                    ->hint(function ($state) use ($peternaks) {
                        if ($peternaks->count() === 0) {
                            return 'Buat data Peternak terlebih dahulu';
                        }
                        return null;
                    })
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('peternak_id')
                    ->label('Peternak')
                    ->formatStateUsing(function ($state): string {  
                        return Peternak::find($state)?->user?->name  
                               ?? 'Deleted Farmer';  
                    })
                    ->searchable(), 
                Tables\Columns\TextColumn::make('no_antri')
                    ->label('Antrian')
                    ->formatStateUsing(function ($record): string {
                        $date = Carbon::parse($record->waktu_daftar)->format('m/d');
                        $id = str_pad($record->no_antri, 3, '0', STR_PAD_LEFT);
                        return "{$date}-{$id}";
                    })
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('quickUpdate')
                    ->button()
                    ->icon('heroicon-o-check') // Optional icon
                    ->disabled(function ($record) {
                        $role = auth()->user()->role;
                        if ($role == 'penyelia') {
                            return $record->validasi_1;
                        } else if ($role == 'juleha') {
                            return $record->validasi_2;
                        }
                    })
                    ->label(function ($record) {
                        $role = auth()->user()->role;
                        if ($role == 'penyelia') {
                            return $record->validasi_1 ? 'Telah divalidasi' : 'Validasi';
                        } else if ($role == 'juleha') {
                            return $record->validasi_2 ? 'Telah divalidasi' : 'Validasi';
                        }
                    })
                    ->action(function ($record) {
                        $role = auth()->user()->role;
                        if ($role == 'penyelia') {
                            $record->update(['validasi_1' => true]); 
                        } else if ($role == 'juleha') {
                            $record->update(['validasi_2' => true]);
                        }
                    })
                    ->color(function ($record) {
                        $role = auth()->user()->role;
                        if ($role == 'penyelia') {
                            return $record->validasi_1 ? 'success' : 'primary';
                        } else if ($role == 'juleha') {
                            return $record->validasi_2 ? 'success' : 'primary';
                        }
                    }) // Button color (e.g., 'primary', 'danger')
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
            'index' => Pages\ListTernaks::route('/'),
            'create' => Pages\CreateTernak::route('/create'),
            'edit' => Pages\EditTernak::route('/{record}/edit'),
        ];
    }
}
