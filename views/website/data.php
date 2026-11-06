<?php
/** Shared marketing content used by the public website pages. */

function demo_trainers(): array
{
    return [
        ['name' => 'Jake Thompson', 'specialty' => 'Strength & Conditioning', 'exp' => 8,  'photo' => 'https://randomuser.me/api/portraits/men/32.jpg', 'bio' => 'Former powerlifting coach. Progressive overload done right.'],
        ['name' => 'Ryan Delgado',  'specialty' => 'Bodybuilding',            'exp' => 10, 'photo' => 'https://randomuser.me/api/portraits/men/22.jpg', 'bio' => 'IFBB competitor and hypertrophy specialist.'],
        ['name' => 'Chris Novak',   'specialty' => 'Cardio & Weight Loss',     'exp' => 6,  'photo' => 'https://randomuser.me/api/portraits/men/45.jpg', 'bio' => 'HIIT and metabolic conditioning coach.'],
        ['name' => 'Sam Whitaker',  'specialty' => 'Mobility & Recovery',      'exp' => 5,  'photo' => 'https://randomuser.me/api/portraits/men/67.jpg', 'bio' => 'Movement quality and injury-prevention coach.'],
    ];
}

function demo_testimonials(): array
{
    return [
        ['name' => 'Alex Carter', 'role' => 'Member since 2025', 'text' => 'Down 6 kg of fat and up 20 kg on my squat in four months. The QR check-in and my coach tracking everything in one app keeps me consistent.', 'initials' => 'AC'],
        ['name' => 'Marcus Reed', 'role' => 'Gym Owner, Iron Paradise', 'text' => 'We run two branches and 500+ members on FitFlow Pro. Renewal reminders alone recovered dozens of memberships we used to lose silently.', 'initials' => 'MR'],
        ['name' => 'Frank Miller', 'role' => 'Member since 2024', 'text' => 'I started at 108 kg. My trainer adjusts my diet plan in the app, I log my weight weekly, and the goal tracker shows exactly how far I have come.', 'initials' => 'FM'],
    ];
}

function demo_posts(): array
{
    return [
        ['slug' => 'progressive-overload-guide', 'title' => 'Progressive Overload: The Only Strength Guide You Need', 'category' => 'Strength', 'date' => 'Nov 2, 2026', 'img' => 'https://images.unsplash.com/photo-1526506118085-60ce8714f8c5?w=800&q=80&auto=format&fit=crop', 'excerpt' => 'Small, consistent increases beat heroic random workouts. Here is how to add weight, reps and sets without stalling.'],
        ['slug' => 'protein-myths', 'title' => '5 Protein Myths That Are Killing Your Gains', 'category' => 'Nutrition', 'date' => 'Oct 26, 2026', 'img' => 'https://images.unsplash.com/photo-1541534741688-6078c6bfb5c5?w=800&q=80&auto=format&fit=crop', 'excerpt' => 'How much protein do you actually need? We break down the research and give you a simple per-kilo formula.'],
        ['slug' => 'hiit-vs-liss', 'title' => 'HIIT vs LISS: Which Cardio Burns More Fat?', 'category' => 'Cardio', 'date' => 'Oct 18, 2026', 'img' => 'https://images.unsplash.com/photo-1538805060514-97d9cc17730c?w=800&q=80&auto=format&fit=crop', 'excerpt' => 'Both work — but for different people and different schedules. A practical framework for choosing (or combining) them.'],
    ];
}

function demo_programs(): array
{
    return [
        ['name' => 'Strength Training', 'icon' => 'bi-lightning-charge', 'img' => 'https://images.unsplash.com/photo-1526506118085-60ce8714f8c5?w=800&q=80&auto=format&fit=crop', 'text' => 'Barbell-first programs built on squats, presses and pulls. Get measurably stronger with coached progressive overload.', 'points' => ['5x5 & 5/3/1 templates', 'Coach-reviewed form checks', 'PR tracking in the app']],
        ['name' => 'Bodybuilding', 'icon' => 'bi-bullseye', 'img' => 'https://images.unsplash.com/photo-1583454110551-21f2fa2afe61?w=800&q=80&auto=format&fit=crop', 'text' => 'Hypertrophy splits — push/pull/legs and upper/lower — with volume landmarks and pump-focused finishers.', 'points' => ['PPL & bro-split options', 'Volume & RPE tracking', 'Posing & check-in photos']],
        ['name' => 'Cardio & Weight Loss', 'icon' => 'bi-heart-pulse', 'img' => 'https://images.unsplash.com/photo-1538805060514-97d9cc17730c?w=800&q=80&auto=format&fit=crop', 'text' => 'HIIT circuits, zone-2 conditioning and nutrition coaching that melts fat while protecting muscle.', 'points' => ['HIIT & LISS mix', 'Weekly weigh-ins + BMI', 'Diet plans that adapt']],
    ];
}
