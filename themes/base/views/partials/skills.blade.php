{{-- Skills grouped by category: $skills (collection of Skill with the category loaded) --}}
@if ($skills->isNotEmpty())
    <div class="skills">
        @foreach ($skills->groupBy('skill_category_id')->sortBy(fn ($group) => $group->first()->category->sort_order ?? 0) as $group)
            <div class="skill-group">
                @if ($group->first()->category)
                    <span class="skill-group-title">{{ $group->first()->category->t('name') }}</span>
                @endif
                <ul class="skill-list">
                    @foreach ($group as $skill)
                        <li>{{ $skill->t('name') }}</li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
@endif
