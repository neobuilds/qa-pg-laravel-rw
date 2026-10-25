<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// QA fixture: read and write one row in the PostgreSQL database from .env.
Route::get('/pgrw', function (\Illuminate\Http\Request $request) {
    $db = \Illuminate\Support\Facades\DB::connection();
    $db->statement('CREATE TABLE IF NOT EXISTS qa_rows (id serial PRIMARY KEY, tag text NOT NULL, at timestamptz DEFAULT now())');
    if ($tag = $request->query('tag')) {
        $db->table('qa_rows')->insert(['tag' => $tag]);
    }
    $who = $db->selectOne('SELECT current_database() AS db, current_user AS usr, inet_server_port() AS port');

    return response()->json([
        'ok' => true, 'driver' => $db->getDriverName(), 'db' => $who->db, 'usr' => $who->usr, 'port' => $who->port,
        'pdo_pgsql' => extension_loaded('pdo_pgsql'), 'php' => PHP_VERSION,
        'rows' => $db->table('qa_rows')->orderBy('id')->get(['id', 'tag']),
        'migrations_table' => \Illuminate\Support\Facades\Schema::hasTable('migrations'),
    ]);
});

Route::get('/pgcross', function (\Illuminate\Http\Request $request) {
    $name = (string) $request->query('db');
    config(['database.connections.qa_cross' => array_merge(config('database.connections.pgsql'), ['database' => $name])]);
    try {
        \Illuminate\Support\Facades\DB::connection('qa_cross')->select('SELECT 1');

        return response()->json(['ok' => true, 'reached' => $name]);
    } catch (\Throwable $e) {
        return response()->json(['ok' => false, 'refused' => $name, 'message' => substr($e->getMessage(), 0, 140)]);
    }
});
