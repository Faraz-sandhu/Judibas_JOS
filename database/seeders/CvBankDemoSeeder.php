<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CvBankDemoSeeder extends Seeder
{
    public function run(): void
    {
        $jobs = [
            ['Laravel Developer', 'Development', 'Build Laravel APIs and Vue interfaces. Experience with PostgreSQL and Git is welcome.'],
            ['Graphic Designer', 'Design', 'Create brand assets, social media creatives and thoughtful interface designs.'],
            ['SEO Specialist', 'SEO', 'Improve technical SEO, research keywords and report on organic search performance.'],
            ['Digital Marketing Executive', 'Marketing', 'Plan campaigns, manage social channels and measure campaign performance.'],
            ['Content Writer', 'Content', 'Write clear website content, articles and product copy.'],
            ['HR Coordinator', 'Human Resources', 'Support recruitment, onboarding and employee coordination.'],
        ];
        $jobIds = [];
        foreach ($jobs as [$title, $department, $description]) {
            DB::table('cv_bank_departments')->insertOrIgnore(['name'=>$department, 'active'=>true, 'created_at'=>now(), 'updated_at'=>now()]);
            $id = DB::table('cv_bank_jobs')->where('title', $title)->where('department', $department)->value('id');
            $jobIds[$department] = $id ?: DB::table('cv_bank_jobs')->insertGetId(['title'=>$title, 'department'=>$department, 'description'=>$description, 'active'=>true, 'created_at'=>now(), 'updated_at'=>now()]);
        }

        $candidates = [
            ['Ayesha Khan', 'ayesha', 'Development', 'new', 'Laravel, Vue, PostgreSQL', false],
            ['Hamza Malik', 'hamza', 'Development', 'reviewing', 'PHP, REST APIs, Git', false],
            ['Sara Ahmed', 'sara', 'Design', 'shortlisted', 'Figma, branding, interface design', false],
            ['Bilal Hussain', 'bilal', 'SEO', 'new', 'Technical SEO, keyword research, analytics', false],
            ['Omar Farooq', 'omar', 'Marketing', 'reviewing', 'Campaign planning, social media, reporting', false],
            ['Fatima Ali', 'fatima', 'Content', 'hired', 'Copywriting, editing, content strategy', false],
            ['Zain Abbas', 'zain', 'Human Resources', 'rejected', 'Recruitment, onboarding, coordination', false],
            ['Maryam Noor', 'maryam', 'Design', 'new', 'Illustration, typography, visual design', true],
            ['Usman Raza', 'usman', 'Development', 'shortlisted', 'JavaScript, CSS, accessibility', true],
        ];
        foreach ($candidates as $index => [$name, $slug, $department, $status, $skills, $general]) {
            $email = $slug.'.cv-demo@example.test';
            if (DB::table('cv_bank_applications')->where('email', $email)->exists()) {
                continue;
            }
            $path = 'cv-bank/demo/'.$slug.'.pdf';
            Storage::disk('local')->put($path, $this->pdf([
                'SAMPLE CV - Fictional candidate for testing only',
                $name,
                'Department: '.$department,
                'Email: '.$email,
                'Skills: '.$skills,
                'Experience: 3 years of sample professional experience.',
                'Education: Sample undergraduate degree.',
                'This document contains no real applicant information.',
            ]));
            DB::table('cv_bank_applications')->insert([
                'job_id'=>$general ? null : $jobIds[$department], 'department'=>$department,
                'name'=>$name.' (Demo)', 'email'=>$email, 'phone'=>null,
                'cover_letter'=>'Sample application for testing the CV Bank workflow. Skills: '.$skills.'.',
                'cv_path'=>$path, 'status'=>$status,
                'notes'=>'Demo application. This candidate and CV are fictional.',
                'created_at'=>now()->subDays($index % 5)->subHours($index), 'updated_at'=>now(),
            ]);
        }
    }

    private function pdf(array $lines): string
    {
        $stream = "BT /F1 12 Tf 50 790 Td 22 TL\n";
        foreach ($lines as $line) {
            $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
            $stream .= '('.$escaped.") Tj T*\n";
        }
        $stream .= 'ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($stream).">>\nstream\n".$stream."\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }
}
