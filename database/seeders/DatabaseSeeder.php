<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Admins
        $admins = [
            [
                'name' => 'Shahin Rahmani',
                'position' => 'Lead Support Specialist',
                'email' => 'shahin@hexsupport.io',
                'phone' => '081289023410',
                'password' => 'password',
            ],
            [
                'name' => 'Admin IT Support',
                'position' => 'Super Admin IT',
                'email' => 'admin@hexsupport.io',
                'phone' => '081234567890',
                'password' => 'password',
            ],
            [
                'name' => 'David Morvarid',
                'position' => 'Senior Network Engineer',
                'email' => 'david@hexsupport.io',
                'phone' => '081122334455',
                'password' => 'password',
            ],
            [
                'name' => 'Nadia Pratama',
                'position' => 'Helpdesk & Hardware Specialist',
                'email' => 'nadia@hexsupport.io',
                'phone' => '081398765432',
                'password' => 'password',
            ],
        ];

        foreach ($admins as $adminData) {
            User::updateOrCreate(
                ['email' => $adminData['email']],
                $adminData
            );
        }

        // 2. Seed Departments (including finance -> Accounting as in prompt specification)
        $departmentsData = [
            [
                'name' => 'Accounting',
                'sub_departments' => ['finance', 'tax', 'treasury', 'billing', 'payroll', 'ap/ar'],
            ],
            [
                'name' => 'Human Resources',
                'sub_departments' => ['hr', 'hrd', 'recruitment', 'training', 'general affair', 'ga'],
            ],
            [
                'name' => 'Operations',
                'sub_departments' => ['logistic', 'warehouse', 'procurement', 'purchasing', 'supply chain', 'production'],
            ],
            [
                'name' => 'Sales & Marketing',
                'sub_departments' => ['marketing', 'sales', 'digital marketing', 'crm', 'branding', 'commercial'],
            ],
            [
                'name' => 'Information Technology',
                'sub_departments' => ['it', 'software', 'network', 'infrastructure', 'security', 'helpdesk'],
            ],
            [
                'name' => 'Legal',
                'sub_departments' => ['legal', 'compliance', 'internal audit', 'corporate secretary'],
            ],
        ];

        $deptMap = [];
        foreach ($departmentsData as $deptItem) {
            $dept = Department::updateOrCreate(
                ['name' => $deptItem['name']],
                ['sub_departments' => $deptItem['sub_departments']]
            );
            $deptMap[$dept->name] = $dept;
        }

        // 3. Seed 28 Realistic Work Orders across the last 30 days ending at IT01518
        // So the next created ticket will be IT01519 (matching the prompt's example!)
        $today = Carbon::today();

        $sampleTickets = [
            [
                'no' => 'IT01491',
                'days_ago' => 27,
                'dept' => 'Accounting',
                'raw_dept' => 'finance',
                'by' => 'Rina Wijaya',
                'loc' => 'Head Office - Lt. 3',
                'desc' => 'Aplikasi Accurate Accounting tidak bisa connect ke database server lokal',
                'status' => 'done',
                'duration' => '1 Jam 15 Menit',
                'solution' => 'Restart service Firebird SQL di server lokal dan konfigurasi ulang IP static workstation.',
            ],
            [
                'no' => 'IT01492',
                'days_ago' => 25,
                'dept' => 'Human Resources',
                'raw_dept' => 'hrd',
                'by' => 'Budi Santoso',
                'loc' => 'Head Office - Lt. 2',
                'desc' => 'Mesin absensi fingerprint di lobby utama gagal sinkronisasi log kehadiran',
                'status' => 'done',
                'duration' => '45 Menit',
                'solution' => 'Reset koneksi LAN mesin Solution X100C dan tarik ulang data log via software attendance.',
            ],
            [
                'no' => 'IT01493',
                'days_ago' => 24,
                'dept' => 'Operations',
                'raw_dept' => 'warehouse',
                'by' => 'Hendra Kusuma',
                'loc' => 'Branch Warehouse Cikarang',
                'desc' => 'Barcode scanner Zebra tidak terbaca di PC admin gudang outbound',
                'status' => 'done',
                'duration' => '30 Menit',
                'solution' => 'Mengganti port USB dan install ulang driver Zebra HID POS Scanner.',
            ],
            [
                'no' => 'IT01494',
                'days_ago' => 22,
                'dept' => 'Marketing & Sales',
                'raw_dept' => 'sales',
                'by' => 'Jessica Tan',
                'loc' => 'Head Office - Lt. 4',
                'desc' => 'Projektor ruang meeting utama tidak menampilkan sinyal HDMI dari laptop Mac',
                'status' => 'done',
                'duration' => '25 Menit',
                'solution' => 'Mengganti konverter Type-C to HDMI dengan unit baru dan atur resolusi ke 1080p 60Hz.',
            ],
            [
                'no' => 'IT01495',
                'days_ago' => 20,
                'dept' => 'Legal & Compliance',
                'raw_dept' => 'legal',
                'by' => 'Dimas Prakoso',
                'loc' => 'Head Office - Lt. 5',
                'desc' => 'Permintaan instalasi Adobe Acrobat Pro untuk tanda tangan digital dokumen kontrak',
                'status' => 'done',
                'duration' => '40 Menit',
                'solution' => 'Instalasi Adobe Acrobat Pro DC berlisensi corporate dan setup sertifikat digital.',
            ],
            [
                'no' => 'IT01496',
                'days_ago' => 18,
                'dept' => 'Accounting',
                'raw_dept' => 'tax',
                'by' => 'Siti Aminah',
                'loc' => 'Head Office - Lt. 3',
                'desc' => 'Aplikasi e-Faktur Pajak error sertifikat elektronik expired saat upload faktur',
                'status' => 'done',
                'duration' => '1 Jam',
                'solution' => 'Import sertifikat elektronik DJP terbaru ke browser dan aplikasi e-Faktur desktop.',
            ],
            [
                'no' => 'IT01497',
                'days_ago' => 17,
                'dept' => 'Operations',
                'raw_dept' => 'procurement',
                'by' => 'Fajar Nugroho',
                'loc' => 'Head Office - Lt. 2',
                'desc' => 'Email Outlook penuh dan tidak bisa menerima lampiran penawaran vendor',
                'status' => 'done',
                'duration' => '1 Jam 30 Menit',
                'solution' => 'Auto-archive email tahun sebelumnya ke file PST lokal dan optimasi mailbox.',
            ],
            [
                'no' => 'IT01498',
                'days_ago' => 15,
                'dept' => 'Marketing & Sales',
                'raw_dept' => 'digital marketing',
                'by' => 'Kevin Alvaro',
                'loc' => 'Branch Office Surabaya',
                'desc' => 'Koneksi Wi-Fi di area kreatif sering terputus saat upload aset video',
                'status' => 'done',
                'duration' => '2 Jam',
                'solution' => 'Re-channeling Access Point Ubiquiti UniFi dan update firmware controller.',
            ],
            [
                'no' => 'IT01499',
                'days_ago' => 14,
                'dept' => 'Human Resources',
                'raw_dept' => 'recruitment',
                'by' => 'Clara Wijaya',
                'loc' => 'Head Office - Lt. 2',
                'desc' => 'Setup laptop baru Lenovo ThinkPad untuk karyawan baru divisi Finance',
                'status' => 'done',
                'duration' => '2 Jam 15 Menit',
                'solution' => 'Join domain Active Directory, instalasi Office 365, VPN FortiClient, dan antivirus.',
            ],
            [
                'no' => 'IT01500',
                'days_ago' => 12,
                'dept' => 'Operations',
                'raw_dept' => 'logistic',
                'by' => 'Rahmat Hidayat',
                'loc' => 'Distribution Center Bekasi',
                'desc' => 'Printer dot matrix Epson LQ-2190 kertas sering macet saat cetak surat jalan',
                'status' => 'done',
                'duration' => '1 Jam 10 Menit',
                'solution' => 'Pembersihan roller tractor, penggantian pita ribbon, dan kalibrasi ketebalan kertas.',
            ],
            [
                'no' => 'IT01501',
                'days_ago' => 11,
                'dept' => 'Accounting',
                'raw_dept' => 'billing',
                'by' => 'Dewi Lestari',
                'loc' => 'Head Office - Lt. 3',
                'desc' => 'File Excel macro rekapitulasi invoice tidak bisa dijalankan (macro blocked)',
                'status' => 'done',
                'duration' => '20 Menit',
                'solution' => 'Menambahkan folder shared network ke Trusted Locations di Trust Center Microsoft Excel.',
            ],
            [
                'no' => 'IT01502',
                'days_ago' => 9,
                'dept' => 'Information Technology',
                'raw_dept' => 'network',
                'by' => 'Arif Rahman',
                'loc' => 'Server Room Head Office',
                'desc' => 'Pengecekan berkala UPS APC 5000VA muncul indikator baterai perlu diganti',
                'status' => 'done',
                'duration' => '3 Jam',
                'solution' => 'Penggantian modul baterai RBC dan kalibrasi runtime UPS tanpa downtime.',
            ],
            [
                'no' => 'IT01503',
                'days_ago' => 8,
                'dept' => 'Marketing & Sales',
                'raw_dept' => 'crm',
                'by' => 'Michael Vaughn',
                'loc' => 'Head Office - Lt. 4',
                'desc' => 'Tidak bisa login ke dashboard CRM karena autentikasi 2FA tidak menerima OTP',
                'status' => 'done',
                'duration' => '35 Menit',
                'solution' => 'Sinkronisasi ulang waktu authenticator dan reset token MFA akun user.',
            ],
            [
                'no' => 'IT01504',
                'days_ago' => 7,
                'dept' => 'Human Resources',
                'raw_dept' => 'general affair',
                'by' => 'Robert Fox',
                'loc' => 'Head Office - Lobby',
                'desc' => 'Layar TV informasi perusahaan di resepsionis tidak menyala otomatis',
                'status' => 'done',
                'duration' => '50 Menit',
                'solution' => 'Ganti kabel power mini PC signage dan aktifkan fitur Wake on AC di BIOS.',
            ],
            [
                'no' => 'IT01505',
                'days_ago' => 6,
                'dept' => 'Operations',
                'raw_dept' => 'production',
                'by' => 'Ollie Pepe',
                'loc' => 'Plant Karawang',
                'desc' => 'PC kontrol lini produksi mengalami Blue Screen of Death (BSOD) saat shift pagi',
                'status' => 'done',
                'duration' => '2 Jam 30 Menit',
                'solution' => 'Cloning SSD ke unit pengganti industrial PC dan update driver chipset.',
            ],
            [
                'no' => 'IT01506',
                'days_ago' => 5,
                'dept' => 'Accounting',
                'raw_dept' => 'treasury',
                'by' => 'David Jenkins',
                'loc' => 'Head Office - Lt. 3',
                'desc' => 'Token KeyBCA dan aplikasi MCM Mandiri tidak terdeteksi di browser Chrome',
                'status' => 'done',
                'duration' => '40 Menit',
                'solution' => 'Update plugin keamanan perbankan dan bersihkan cache SSL.',
            ],
            [
                'no' => 'IT01507',
                'days_ago' => 4,
                'dept' => 'Legal & Compliance',
                'raw_dept' => 'compliance',
                'by' => 'Putri Maharani',
                'loc' => 'Head Office - Lt. 5',
                'desc' => 'Akses folder bersama (Shared Drive) divisi Legal meminta kredensial berulang',
                'status' => 'done',
                'duration' => '30 Menit',
                'solution' => 'Reset Windows Credential Manager dan re-map network drive menggunakan GPO.',
            ],
            [
                'no' => 'IT01508',
                'days_ago' => 3,
                'dept' => 'Marketing & Sales',
                'raw_dept' => 'branding',
                'by' => 'Reza Oktavian',
                'loc' => 'Head Office - Lt. 4',
                'desc' => 'Upgrade RAM iMac desain grafis dan pembersihan scratch disk Photoshop',
                'status' => 'done',
                'duration' => '1 Jam 45 Menit',
                'solution' => 'Pasang modul 32GB DDR4 tambahan dan alihkan scratch disk ke NVMe eksternal.',
            ],
            [
                'no' => 'IT01509',
                'days_ago' => 3,
                'dept' => 'Operations',
                'raw_dept' => 'supply chain',
                'by' => 'Agus Salim',
                'loc' => 'Branch Warehouse Cikarang',
                'desc' => 'Koneksi VPN site-to-site Cikarang ke Head Office latency tinggi (>250ms)',
                'status' => 'open',
                'duration' => null,
                'solution' => null,
            ],
            [
                'no' => 'IT01510',
                'days_ago' => 2,
                'dept' => 'Accounting',
                'raw_dept' => 'payroll',
                'by' => 'Linda Kusuma',
                'loc' => 'Head Office - Lt. 3',
                'desc' => 'Permintaan restore backup file perhitungan lembur bulan lalu dari NAS',
                'status' => 'done',
                'duration' => '35 Menit',
                'solution' => 'Restore snapshot harian dari Synology NAS ke folder kerja user.',
            ],
            [
                'no' => 'IT01511',
                'days_ago' => 2,
                'dept' => 'Human Resources',
                'raw_dept' => 'training',
                'by' => 'Farhan Hakim',
                'loc' => 'Training Center Lt. 1',
                'desc' => 'Audio speaker ruang training berdengung saat konferensi Zoom berlangsung',
                'status' => 'open',
                'duration' => null,
                'solution' => null,
            ],
            [
                'no' => 'IT01512',
                'days_ago' => 1,
                'dept' => 'Operations',
                'raw_dept' => 'purchasing',
                'by' => 'Maya Sari',
                'loc' => 'Head Office - Lt. 2',
                'desc' => 'Monitor kedua Dell 24 inch berkedip (flickering) saat membuka aplikasi ERP',
                'status' => 'done',
                'duration' => '45 Menit',
                'solution' => 'Mengganti kabel DisplayPort dan menonaktifkan fitur G-Sync/VRR pada driver GPU.',
            ],
            [
                'no' => 'IT01513',
                'days_ago' => 1,
                'dept' => 'Marketing & Sales',
                'raw_dept' => 'sales',
                'by' => 'Tommy Kurniawan',
                'loc' => 'Branch Office Bandung',
                'desc' => 'Instalasi aplikasi softphone IP PBX di smartphone dan laptop tim sales',
                'status' => 'open',
                'duration' => null,
                'solution' => null,
            ],
            [
                'no' => 'IT01514',
                'days_ago' => 1,
                'dept' => 'Legal & Compliance',
                'raw_dept' => 'internal audit',
                'by' => 'Yudha Pratama',
                'loc' => 'Head Office - Lt. 5',
                'desc' => 'Enkripsi BitLocker laptop audit meminta recovery key setelah update BIOS',
                'status' => 'done',
                'duration' => '25 Menit',
                'solution' => 'Input BitLocker Recovery Key dari portal Azure AD dan resume proteksi TPM.',
            ],
            [
                'no' => 'IT01515',
                'days_ago' => 0,
                'dept' => 'Accounting',
                'raw_dept' => 'finance',
                'by' => 'Alfin',
                'loc' => 'Head Office',
                'desc' => 'Printer Canon IR lantai 3 muncul kode error E00007-0000 saat cetak laporan',
                'status' => 'open',
                'duration' => null,
                'solution' => null,
            ],
            [
                'no' => 'IT01516',
                'days_ago' => 0,
                'dept' => 'Human Resources',
                'raw_dept' => 'hr',
                'by' => 'Nadia Larasati',
                'loc' => 'Head Office - Lt. 2',
                'desc' => 'Pembuatan akun email corporate dan akses portal HRIS untuk 3 staf baru',
                'status' => 'done',
                'duration' => '30 Menit',
                'solution' => 'Pembuatan akun Google Workspace, grup distribusi divisi, dan kredensial HRIS.',
            ],
            [
                'no' => 'IT01517',
                'days_ago' => 0,
                'dept' => 'Operations',
                'raw_dept' => 'warehouse',
                'by' => 'Bagus Setiawan',
                'loc' => 'Branch Warehouse Cikarang',
                'desc' => 'Printer thermal Zebra ZD220 hasil cetak label resi logistik buram separuh',
                'status' => 'open',
                'duration' => null,
                'solution' => null,
            ],
            [
                'no' => 'IT01518',
                'days_ago' => 0,
                'dept' => 'Marketing & Sales',
                'raw_dept' => 'marketing',
                'by' => 'Siska Amelia',
                'loc' => 'Head Office - Lt. 4',
                'desc' => 'Sinkronisasi OneDrive macet pada status Processing Changes lebih dari 2 jam',
                'status' => 'open',
                'duration' => null,
                'solution' => null,
            ],
        ];

        foreach ($sampleTickets as $item) {
            $reqDate = $today->copy()->subDays($item['days_ago']);
            $deadlineDate = $reqDate->copy()->addDays(2);
            $deptObj = $deptMap[$item['dept']] ?? null;

            $wo = WorkOrder::updateOrCreate(
                ['work_order_no' => $item['no']],
                [
                    'department_id' => $deptObj?->id,
                    'department_name' => $item['dept'],
                    'raw_department' => $item['raw_dept'],
                    'requested_by' => $item['by'],
                    'date_requested' => $reqDate->format('Y-m-d'),
                    'completion_deadline' => $deadlineDate->format('Y-m-d'),
                    'location' => $item['loc'],
                    'description' => $item['desc'],
                    'status' => $item['status'],
                    'duration' => $item['duration'],
                    'solution' => $item['solution'],
                    'completed_at' => $item['status'] === 'done' ? $reqDate->copy()->addHours(3) : null,
                ]
            );

            // Set created_at to match reqDate so today's count and historical timestamps are accurate
            $wo->timestamps = false;
            $wo->created_at = $reqDate->copy()->setTime(9, 15, 0);
            $wo->updated_at = $item['status'] === 'done'
                ? $reqDate->copy()->setTime(11, 45, 0)
                : $reqDate->copy()->setTime(9, 15, 0);
            $wo->save();
        }
    }
}
