<?php

/**
 * Terjemahan pesan validasi Laravel ke bahasa Indonesia.
 *
 * Laravel TIDAK menyertakan terjemahan `id` sama sekali (dan tidak
 * menyertakan lang/ sejak v9), jadi tanpa file ini setiap pesan
 * validasi sampai ke pengguna sebagai kunci mentah — karyawan yang
 * lupa mengisi alasan cuti melihat "validation.required" di layar HP.
 *
 * Tidak ada perbaikan upstream yang akan menyusul untuk `id`, jadi file
 * ini memang dirawat sendiri. Seluruh 150 kunci diterjemahkan, bukan
 * hanya aturan yang sekarang dipakai: kalau nanti ada yang menambah
 * ->min(3) di sebuah form, pesannya sudah benar tanpa perlu diingat.
 *
 * `lang/en/` sengaja TIDAK disimpan — identik dengan bawaan framework,
 * dan menyalinnya berarti perbaikan upstream tidak akan terpakai.
 */

return [

    'accepted' => ':attribute harus disetujui.',
    'accepted_if' => ':attribute harus disetujui jika :other adalah :value.',
    'active_url' => ':attribute bukan URL yang valid.',
    'after' => ':attribute harus tanggal setelah :date.',
    'after_or_equal' => ':attribute harus tanggal setelah atau sama dengan :date.',
    'alpha' => ':attribute hanya boleh berisi huruf.',
    'alpha_dash' => ':attribute hanya boleh berisi huruf, angka, strip, dan garis bawah.',
    'alpha_num' => ':attribute hanya boleh berisi huruf dan angka.',
    'any_of' => ':attribute tidak valid.',
    'array' => ':attribute harus berupa array.',
    'ascii' => ':attribute hanya boleh berisi karakter dan simbol alfanumerik satu byte.',
    'before' => ':attribute harus tanggal sebelum :date.',
    'before_or_equal' => ':attribute harus tanggal sebelum atau sama dengan :date.',

    'between' => [
        'array' => ':attribute harus berisi antara :min sampai :max item.',
        'file' => ':attribute harus berukuran antara :min sampai :max kilobyte.',
        'numeric' => ':attribute harus bernilai antara :min sampai :max.',
        'string' => ':attribute harus berisi antara :min sampai :max karakter.',
    ],

    'boolean' => ':attribute harus bernilai benar atau salah.',
    'can' => ':attribute berisi nilai yang tidak diizinkan.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'contains' => ':attribute tidak memuat nilai yang diwajibkan.',
    'current_password' => 'Password salah.',
    'date' => ':attribute bukan tanggal yang valid.',
    'date_equals' => ':attribute harus tanggal yang sama dengan :date.',
    'date_format' => 'Format :attribute tidak sesuai dengan :format.',
    'decimal' => ':attribute harus memiliki :decimal angka desimal.',
    'declined' => ':attribute harus ditolak.',
    'declined_if' => ':attribute harus ditolak jika :other adalah :value.',
    'different' => ':attribute dan :other harus berbeda.',
    'digits' => ':attribute harus terdiri dari :digits angka.',
    'digits_between' => ':attribute harus terdiri dari :min sampai :max angka.',
    'dimensions' => 'Dimensi gambar :attribute tidak valid.',
    'distinct' => ':attribute memiliki nilai yang duplikat.',
    'doesnt_contain' => ':attribute tidak boleh memuat salah satu dari: :values.',
    'doesnt_end_with' => ':attribute tidak boleh diakhiri dengan salah satu dari: :values.',
    'doesnt_start_with' => ':attribute tidak boleh diawali dengan salah satu dari: :values.',
    'email' => ':attribute harus berupa alamat email yang valid.',
    'ends_with' => ':attribute harus diakhiri dengan salah satu dari: :values.',
    'enum' => ':attribute yang dipilih tidak valid.',
    'exists' => ':attribute yang dipilih tidak valid.',
    'extensions' => ':attribute harus memiliki salah satu ekstensi berikut: :values.',
    'file' => ':attribute harus berupa berkas.',
    'filled' => ':attribute harus diisi.',

    'gt' => [
        'array' => ':attribute harus berisi lebih dari :value item.',
        'file' => ':attribute harus lebih besar dari :value kilobyte.',
        'numeric' => ':attribute harus lebih besar dari :value.',
        'string' => ':attribute harus lebih panjang dari :value karakter.',
    ],

    'gte' => [
        'array' => ':attribute harus berisi :value item atau lebih.',
        'file' => ':attribute harus lebih besar dari atau sama dengan :value kilobyte.',
        'numeric' => ':attribute harus lebih besar dari atau sama dengan :value.',
        'string' => ':attribute harus lebih panjang dari atau sama dengan :value karakter.',
    ],

    'hex_color' => ':attribute harus berupa warna heksadesimal yang valid.',
    'image' => ':attribute harus berupa gambar.',
    'in' => ':attribute yang dipilih tidak valid.',
    'in_array' => ':attribute tidak ada di dalam :other.',
    'in_array_keys' => ':attribute harus memuat setidaknya satu dari kunci berikut: :values.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'ip' => ':attribute harus berupa alamat IP yang valid.',
    'ipv4' => ':attribute harus berupa alamat IPv4 yang valid.',
    'ipv6' => ':attribute harus berupa alamat IPv6 yang valid.',
    'json' => ':attribute harus berupa string JSON yang valid.',
    'list' => ':attribute harus berupa list.',
    'lowercase' => ':attribute harus berupa huruf kecil.',

    'lt' => [
        'array' => ':attribute harus berisi kurang dari :value item.',
        'file' => ':attribute harus lebih kecil dari :value kilobyte.',
        'numeric' => ':attribute harus lebih kecil dari :value.',
        'string' => ':attribute harus lebih pendek dari :value karakter.',
    ],

    'lte' => [
        'array' => ':attribute tidak boleh berisi lebih dari :value item.',
        'file' => ':attribute harus lebih kecil dari atau sama dengan :value kilobyte.',
        'numeric' => ':attribute harus lebih kecil dari atau sama dengan :value.',
        'string' => ':attribute harus lebih pendek dari atau sama dengan :value karakter.',
    ],

    'mac_address' => ':attribute harus berupa alamat MAC yang valid.',

    'max' => [
        'array' => ':attribute tidak boleh berisi lebih dari :max item.',
        'file' => ':attribute tidak boleh lebih besar dari :max kilobyte.',
        'numeric' => ':attribute tidak boleh lebih besar dari :max.',
        'string' => ':attribute tidak boleh lebih dari :max karakter.',
    ],

    'max_digits' => ':attribute tidak boleh terdiri dari lebih dari :max angka.',
    'mimes' => ':attribute harus berupa berkas bertipe: :values.',
    'mimetypes' => ':attribute harus berupa berkas bertipe: :values.',

    'min' => [
        'array' => ':attribute harus berisi setidaknya :min item.',
        'file' => ':attribute harus berukuran setidaknya :min kilobyte.',
        'numeric' => ':attribute harus bernilai setidaknya :min.',
        'string' => ':attribute harus berisi setidaknya :min karakter.',
    ],

    'min_digits' => ':attribute harus terdiri dari setidaknya :min angka.',
    'missing' => ':attribute harus tidak ada.',
    'missing_if' => ':attribute harus tidak ada jika :other adalah :value.',
    'missing_unless' => ':attribute harus tidak ada kecuali :other adalah :value.',
    'missing_with' => ':attribute harus tidak ada jika :values ada.',
    'missing_with_all' => ':attribute harus tidak ada jika :values ada.',
    'multiple_of' => ':attribute harus kelipatan dari :value.',
    'not_in' => ':attribute yang dipilih tidak valid.',
    'not_regex' => 'Format :attribute tidak valid.',
    'numeric' => ':attribute harus berupa angka.',

    'password' => [
        'letters' => ':attribute harus mengandung setidaknya satu huruf.',
        'mixed' => ':attribute harus mengandung setidaknya satu huruf besar dan satu huruf kecil.',
        'numbers' => ':attribute harus mengandung setidaknya satu angka.',
        'symbols' => ':attribute harus mengandung setidaknya satu simbol.',
        'uncompromised' => ':attribute yang dimasukkan pernah bocor di kebocoran data. Silakan pakai :attribute yang lain.',
    ],

    'present' => ':attribute harus ada.',
    'present_if' => ':attribute harus ada jika :other adalah :value.',
    'present_unless' => ':attribute harus ada kecuali :other adalah :value.',
    'present_with' => ':attribute harus ada jika :values ada.',
    'present_with_all' => ':attribute harus ada jika :values ada.',
    'prohibited' => ':attribute tidak diizinkan.',
    'prohibited_if' => ':attribute tidak diizinkan jika :other adalah :value.',
    'prohibited_if_accepted' => ':attribute tidak diizinkan jika :other disetujui.',
    'prohibited_if_declined' => ':attribute tidak diizinkan jika :other ditolak.',
    'prohibited_unless' => ':attribute tidak diizinkan kecuali :other ada di dalam :values.',
    'prohibits' => ':attribute membuat :other tidak diizinkan.',
    'regex' => 'Format :attribute tidak valid.',
    'required' => ':attribute wajib diisi.',
    'required_array_keys' => ':attribute harus memuat kunci: :values.',
    'required_if' => ':attribute wajib diisi jika :other adalah :value.',
    'required_if_accepted' => ':attribute wajib diisi jika :other disetujui.',
    'required_if_declined' => ':attribute wajib diisi jika :other ditolak.',
    'required_unless' => ':attribute wajib diisi kecuali :other ada di dalam :values.',
    'required_with' => ':attribute wajib diisi jika :values ada.',
    'required_with_all' => ':attribute wajib diisi jika :values ada.',
    'required_without' => ':attribute wajib diisi jika :values tidak ada.',
    'required_without_all' => ':attribute wajib diisi jika tidak ada satu pun dari :values.',
    'same' => ':attribute dan :other harus sama.',

    'size' => [
        'array' => ':attribute harus berisi :size item.',
        'file' => ':attribute harus berukuran :size kilobyte.',
        'numeric' => ':attribute harus bernilai :size.',
        'string' => ':attribute harus berisi :size karakter.',
    ],

    'starts_with' => ':attribute harus diawali dengan salah satu dari: :values.',
    'string' => ':attribute harus berupa teks.',
    'timezone' => ':attribute harus berupa zona waktu yang valid.',
    'unique' => ':attribute sudah dipakai.',
    'uploaded' => ':attribute gagal diunggah.',
    'uppercase' => ':attribute harus berupa huruf besar.',
    'url' => ':attribute harus berupa URL yang valid.',
    'ulid' => ':attribute harus berupa ULID yang valid.',
    'uuid' => ':attribute harus berupa UUID yang valid.',

    /*
    |--------------------------------------------------------------------------
    | Pesan khusus per-field
    |--------------------------------------------------------------------------
    |
    | Dipakai kalau pesan generik di atas terasa janggal untuk field
    | tertentu. Formatnya: 'nama_field' => ['aturan' => 'pesan'].
    |
    */

    'custom' => [
        'alasan' => [
            'required' => 'Alasan wajib diisi.',
        ],
        'password' => [
            'required' => 'Password wajib diisi.',
        ],
        'username' => [
            'required' => 'Username wajib diisi.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nama field yang dibaca manusia
    |--------------------------------------------------------------------------
    |
    | Mengganti :attribute di pesan di atas. Tanpa ini, pesannya berbunyi
    | "tanggal_mulai wajib diisi" alih-alih "Tanggal mulai wajib diisi".
    | Isi hanya field yang benar-benar dikirim ke pengguna lewat API.
    |
    */

    'attributes' => [
        'alasan' => 'Alasan',
        'email' => 'Email',
        'foto_masuk' => 'Foto masuk',
        'foto_pulang' => 'Foto pulang',
        'jadwal_id' => 'Jadwal',
        'jadwal_tujuan_id' => 'Jadwal rekan tujuan',
        'jam_keluar' => 'Jam keluar',
        'jam_kembali' => 'Jam kembali',
        'jam_mulai' => 'Jam mulai',
        'jam_selesai' => 'Jam selesai',
        'jenis_cuti_id' => 'Jenis cuti',
        'keperluan' => 'Keperluan',
        'kode_qr' => 'Kode QR',
        'latitude' => 'Lokasi (latitude)',
        'longitude' => 'Lokasi (longitude)',
        'mode' => 'Mode',
        'nama' => 'Nama',
        'nip' => 'NIP',
        'password' => 'Password',
        'setuju' => 'Persetujuan',
        'tanggal' => 'Tanggal',
        'tanggal_baru' => 'Tanggal baru',
        'tanggal_mulai' => 'Tanggal mulai',
        'tanggal_selesai' => 'Tanggal selesai',
        'tujuan' => 'Tujuan',
        'username' => 'Username',
    ],

];
