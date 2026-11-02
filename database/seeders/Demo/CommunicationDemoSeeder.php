<?php

namespace Database\Seeders\Demo;

use App\Enums\Communication\HomeworkStatus;
use App\Enums\Communication\NoticeAudienceType;
use App\Enums\Communication\NoticeStatus;
use App\Models\Communication\Homework;
use App\Models\Communication\Notice;
use App\Models\Communication\NoticeAudience;
use App\Models\Platform\School;
use App\Models\SchoolSetup\AcademicYear;
use App\Models\SchoolSetup\SchoolClass;
use App\Models\Staff\TeacherAssignment;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Synthetic homework and notices for the demo schools (sunrise, greenvalley). Safe to run twice: a school that
 * already has notices is skipped.
 */
class CommunicationDemoSeeder extends Seeder
{
    private const HOMEWORK = [
        ['Exercise 3.2 - fractions', "Solve questions 1 to 10 in your notebook.\nShow every step of your working."],
        ['Read chapter 5', "Read the chapter twice and underline new words.\nWrite the meaning of any five of them."],
        ['Poem recitation', 'Learn the first two stanzas of the poem by heart for recitation in class.'],
        ['Worksheet: plants around us', "Complete the attached-style worksheet from your activity book (page 34).\nColour the diagram neatly."],
        ['Essay: my favourite festival', 'Write 120-150 words. Use proper paragraphs and check your spellings.'],
        ['Map work', 'Mark the major rivers of India on the outline map given in class.'],
    ];

    public function run(): void
    {
        foreach (['sunrise', 'greenvalley'] as $code) {
            $school = School::query()->where('code', $code)->first();
            if ($school === null) {
                continue;
            }

            app(TenantContext::class)->run($school, function () use ($school) {
                if (Notice::query()->exists()) {
                    $this->command?->line("Homework & notices for {$school->name} already exist - skipped.");

                    return;
                }

                $domain = $school->code === 'sunrise' ? 'sunrise.test' : 'greenvalley.test';
                $admin = User::query()->where('email', "admin@{$domain}")->first();
                $teacher = User::query()->where('email', "teacher@{$domain}")->first();

                $this->notices($admin);
                $this->homework($teacher ?? $admin);
            });
        }
    }

    private function notices(?User $admin): void
    {
        $by = $admin?->getKey();
        $classes = SchoolClass::query()->orderBy('sort_order')->get();
        $teacherRole = \App\Models\Identity\Role::query()->where('slug', 'teacher')->value('id');

        $make = function (array $attributes, array $audiences) use ($by): void {
            $notice = Notice::create($attributes + ['created_by' => $by, 'updated_by' => $by, 'published_by' => ($attributes['status'] ?? null) === NoticeStatus::Published ? $by : null]);
            foreach ($audiences as $audience) {
                NoticeAudience::create(['notice_id' => $notice->id] + $audience);
            }
        };

        $make([
            'title' => 'Parent-teacher meeting this Saturday',
            'body' => "Dear parents,\n\nThe parent-teacher meeting for all classes is on Saturday from 9:00 AM to 12:00 PM.\nPlease bring your ward's diary.\n\nPrincipal",
            'status' => NoticeStatus::Published, 'is_pinned' => true, 'publish_at' => now()->subDays(2), 'published_at' => now()->subDays(2),
        ], [['type' => NoticeAudienceType::School]]);

        $make([
            'title' => 'Staff meeting after school on Friday',
            'body' => "All teaching and office staff are requested to attend a short meeting in the staff room at 2:30 PM.\nAgenda: half-yearly exam duties.",
            'status' => NoticeStatus::Published, 'publish_at' => now()->subDay(), 'published_at' => now()->subDay(),
        ], [['type' => NoticeAudienceType::Staff]]);

        $make([
            'title' => 'Second-quarter fee reminder',
            'body' => "The second-quarter fee is due by the 10th.\nPayments can be made at the school office between 9 AM and 1 PM.",
            'status' => NoticeStatus::Published, 'publish_at' => now()->subHours(6), 'published_at' => now()->subHours(6), 'expires_at' => now()->addDays(20),
        ], [['type' => NoticeAudienceType::Parents]]);

        if ($classes->count() >= 2) {
            $make([
                'title' => 'Science exhibition - project submission',
                'body' => "Students of these classes should submit their science models by next Wednesday.\nTeachers will share the topic list in class.",
                'status' => NoticeStatus::Published, 'publish_at' => now()->subHours(3), 'published_at' => now()->subHours(3),
            ], $classes->slice(-2)->map(fn (SchoolClass $c) => ['type' => NoticeAudienceType::SchoolClass, 'school_class_id' => $c->id])->values()->all());
        }

        if ($teacherRole) {
            $make([
                'title' => 'Submit lesson plans for next month',
                'body' => 'Teachers, please upload or hand in your lesson plans for next month by the 25th.',
                'status' => NoticeStatus::Published, 'publish_at' => now()->subHours(1), 'published_at' => now()->subHours(1),
            ], [['type' => NoticeAudienceType::Role, 'role_id' => $teacherRole]]);
        }

        $make([
            'title' => 'Winter uniform from 1st December',
            'body' => 'Students may wear the winter uniform (navy blazer or sweater) from 1st December.',
            'status' => NoticeStatus::Published, 'publish_at' => now()->addDays(3), 'published_at' => now(),
        ], [['type' => NoticeAudienceType::School]]);

        $make([
            'title' => 'Annual day rehearsal schedule (draft)',
            'body' => 'Rehearsal timings will be shared class-wise. Draft - to be confirmed.',
            'status' => NoticeStatus::Draft,
        ], [['type' => NoticeAudienceType::School]]);

        $make([
            'title' => 'Dussehra holiday',
            'body' => 'The school remained closed for Dussehra.',
            'status' => NoticeStatus::Published, 'publish_at' => now()->subDays(20), 'published_at' => now()->subDays(20), 'expires_at' => now()->subDays(8),
        ], [['type' => NoticeAudienceType::School]]);
    }

    private function homework(?User $author): void
    {
        $year = AcademicYear::query()->where('is_current', true)->first();
        if ($year === null) {
            return;
        }

        $assignments = TeacherAssignment::query()
            ->where('academic_year_id', $year->id)
            ->whereNotNull('subject_id')
            ->whereNotNull('section_id')
            ->orderBy('id')
            ->get();

        // Prefer the demo teacher's own classes so "teacher@..." sees and can edit them.
        $own = $author === null ? collect() : $assignments->filter(fn (TeacherAssignment $a) => $a->staffProfile()->value('user_id') === $author->getKey());
        $picked = $own->concat($assignments->diff($own))->take(12)->values();
        $today = school_today();

        foreach ($picked as $i => $assignment) {
            [$title, $description] = self::HOMEWORK[$i % count(self::HOMEWORK)];
            $assigned = $today->subDays($i % 5);
            $due = $assigned->addDays([1, 2, 3, 5, 7, -1][$i % 6] > 0 ? [1, 2, 3, 5, 7, 4][$i % 6] : 2);

            Homework::create([
                'academic_year_id' => $year->id,
                'school_class_id' => $assignment->school_class_id,
                'section_id' => $i % 4 === 3 ? null : $assignment->section_id,
                'subject_id' => $assignment->subject_id,
                'title' => $title,
                'description' => $description,
                'assigned_on' => $assigned->toDateString(),
                'due_on' => $due->toDateString(),
                'status' => $i === 11 ? HomeworkStatus::Archived : HomeworkStatus::Active,
                'archived_at' => $i === 11 ? now() : null,
                'created_by' => $author?->getKey(),
                'updated_by' => $author?->getKey(),
            ]);
        }
    }
}
