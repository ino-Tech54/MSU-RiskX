<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            // ── FACULTIES ─────────────────────────────────────────────────────────
            [
                'id' => 'dept-fac-001',
                'name' => 'Agriculture, Environment and Natural Resources Management',
                'type' => 'Faculty',
                'subs' => [
                    'Agricultural Economics and Development',
                    'Agronomy and Horticulture',
                    'Animal and Wildlife Sciences',
                    'Land and Water Resources Management',
                ],
            ],
            [
                'id' => 'dept-fac-002',
                'name' => 'Arts and Humanities',
                'type' => 'Faculty',
                'subs' => [
                    'Department of Archeology, Cultural Heritage and Museum Studies',
                    'Department of Development Studies',
                    'Department of History, Heritage and International Studies',
                    'Department of Languages, Literature and Cultural Studies',
                    'Department of Media, Communication, Film and Theatre Studies',
                    'Department of Religious Studies and Ethics',
                    'Communication Skill Centre',
                ],
            ],
            [
                'id' => 'dept-fac-003',
                'name' => 'Built Environment, Art and Design',
                'type' => 'Faculty',
                'subs' => [
                    'Architecture',
                    'Civil Engineering',
                    'Estate Management',
                    'Quantity Surveying',
                    'Regional and Urban Planning',
                    'Surveying and Geomatics',
                ],
            ],
            [
                'id' => 'dept-fac-004',
                'name' => 'Business Sciences',
                'type' => 'Faculty',
                'subs' => [
                    'Department of Accounting Sciences',
                    'Department of Economic Sciences',
                    'Department of Information and Marketing Sciences',
                    'Department of Supply Chain, Insurance and Risk Sciences',
                    'Department of Management Sciences',
                    'Department of Tourism, Hospitality and Leisure Sciences',
                    'Centre for Entrepreneurship and Innovation',
                    'Graduate School of Business Leadership',
                ],
            ],
            [
                'id' => 'dept-fac-005',
                'name' => 'Education',
                'type' => 'Faculty',
                'subs' => [
                    'Educational Foundations, Primary Education and Pedagogy',
                    'Educational Policy Studies and Leadership',
                    'Humanities, Business Development and Arts Education',
                    'Science, Technology and Design Education',
                ],
            ],
            [
                'id' => 'dept-fac-006',
                'name' => 'Engineering and Geosciences',
                'type' => 'Faculty',
                'subs' => [
                    'Mining Engineering Department',
                    'Metallurgical and Materials Engineering Department',
                    'Department of Geosciences',
                    'Mechanical Engineering',
                    'Fuels & Energy Engineering',
                ],
            ],
            [
                'id' => 'dept-fac-007',
                'name' => 'Law',
                'type' => 'Faculty',
                'subs' => [], // No sub-departments
            ],
            [
                'id' => 'dept-fac-008',
                'name' => 'Medicine and Health Sciences',
                'type' => 'Faculty',
                'subs' => [
                    'Anaesthesia and Critical Medicine',
                    'Anatomy',
                    'Biochemistry',
                    'Biomedical Sciences',
                    'Chemical Pathology',
                    'Clinical Pharmacology',
                    'Community Medicine',
                    'Haematology',
                    'Histopathology',
                    'Obstetrics and Gynecology',
                    'Physiology',
                    'Psychiatry',
                    'Rehabilitation',
                    'Surgery',
                ],
            ],
            [
                'id' => 'dept-fac-009',
                'name' => 'Science and Technology',
                'type' => 'Faculty',
                'subs' => [
                    'Applied Biosciences & Biotechnology',
                    'Applied Mathematics and Statistics',
                    'Applied Physics and Telecommunications',
                    'Chemical Sciences',
                    'Computer Science',
                    'Food Science and Nutrition',
                ],
            ],
            [
                'id' => 'dept-fac-010',
                'name' => 'Social Sciences',
                'type' => 'Faculty',
                'subs' => [
                    'Applied Psychology',
                    'Community Studies',
                    'Geography, Environmental Sustainability and Resilience Building',
                    'Governance and Public Management',
                    'Human Resource Management',
                    'Music Business, Musicology and Technology',
                    'Peace and Security Studies',
                    'School of Social Work',
                ],
            ],

            // ── NON-FACULTIES ─────────────────────────────────────────────────────
            [
                'id' => 'dept-nf-001',
                'name' => 'Bursar Department',
                'type' => 'Non-Faculty',
                'subs' => [
                    // Accounting & Finance flattened with units
                    'Accounting & Finance - Cashbook',
                    'Accounting & Finance - Student Accounts',
                    'Accounting & Finance - Salaries Office',
                    'Accounting & Finance - Accounts Payables',
                    'Accounting & Finance - Receivables',
                    'Accounting & Finance - Assets',
                    // Planning and Systems has no sub-units
                    'Planning and Systems',
                ],
            ],
            [
                'id' => 'dept-nf-002',
                'name' => 'Disability Resource Centre',
                'type' => 'Non-Faculty',
                'subs' => [],
            ],
            [
                'id' => 'dept-nf-003',
                'name' => 'Quality Assurance and Professional Development Directorate',
                'type' => 'Non-Faculty',
                'subs' => [],
            ],
            [
                'id' => 'dept-nf-004',
                'name' => 'Human Resources Management',
                'type' => 'Non-Faculty',
                'subs' => [
                    'Performance Contracting and Compliance',
                ],
            ],
            [
                'id' => 'dept-nf-005',
                'name' => 'International Relations',
                'type' => 'Non-Faculty',
                'subs' => [
                    'Admissions',
                    'Visa & Study Permit',
                    'Student Affairs',
                ],
            ],
            [
                'id' => 'dept-nf-006',
                'name' => 'Information Technology Services Department',
                'type' => 'Non-Faculty',
                'subs' => [],
            ],
            [
                'id' => 'dept-nf-007',
                'name' => 'Marketing and Communications',
                'type' => 'Non-Faculty',
                'subs' => [],
            ],
            [
                'id' => 'dept-nf-008',
                'name' => 'MSU Enterprises',
                'type' => 'Non-Faculty',
                'subs' => [
                    'Chemical Manufacturing',
                    'Clothing and Textile',
                    'Food and Beverages',
                    'Press and Publications',
                    'OD Stores',
                    'Agribusiness',
                ],
            ],
            [
                'id' => 'dept-nf-009',
                'name' => 'MSU National Pathology Research and Diagnostic Centre',
                'type' => 'Non-Faculty',
                'subs' => [],
            ],
            [
                'id' => 'dept-nf-010',
                'name' => 'Student Affairs',
                'type' => 'Non-Faculty',
                'subs' => [
                    'Chaplaincy',
                    'Counselling Services',
                    'Health Services',
                    'Residence',
                    'Student Development',
                    'Sports and Recreation',
                    'SRC Constitution',
                ],
            ],
        ];

        foreach ($data as $dept) {
            // Skip if department with this ID already exists
            $exists = DB::table('departments')->where('department_id', $dept['id'])->exists();
            if (!$exists) {
                DB::table('departments')->insert([
                    'department_id'   => $dept['id'],
                    'department_name' => $dept['name'],
                    'department_code' => strtoupper(str_replace('dept-', '', $dept['id'])),
                    'type'            => $dept['type'],
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }

            // Always sync sub-departments (delete and re-insert for idempotency)
            DB::table('sub_departments')->where('department_id', $dept['id'])->delete();
            foreach ($dept['subs'] as $subName) {
                DB::table('sub_departments')->insert([
                    'id'            => (string) Str::uuid(),
                    'department_id' => $dept['id'],
                    'name'          => $subName,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
        }

        $this->command->info('Departments and sub-departments seeded successfully.');
    }
}
