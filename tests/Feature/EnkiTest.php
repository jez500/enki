<?php

use App\Models\Author;
use App\Models\Category;
use App\Models\Skill;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('enki'))->assertRedirect(route('login'));
});

test('authenticated users can visit enki', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('enki'))->assertOk();
});

test('enki page includes skills from the database', function () {
    $author = Author::factory()->create();
    $category = Category::factory()->create(['slug' => 'coding', 'label' => 'Coding']);
    $skill = Skill::factory()->for($author)->create(['slug' => 'coding/test-skill', 'tags' => ['php', 'testing']]);
    $skill->categories()->attach($category);

    $this->actingAs(User::factory()->create());

    $this->get(route('enki'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Enki')
            ->where('skills.data.0.slug', 'coding/test-skill')
            ->where('skills.data.0.tags', ['php', 'testing'])
            ->where('skills.data.0.categories', ['coding'])
            ->where('skills.data.0.starred', false)
        );
});

test('starred skills are flagged for the authenticated user', function () {
    $author = Author::factory()->create();
    $skill = Skill::factory()->for($author)->create(['slug' => 'coding/starred-skill']);

    $user = User::factory()->create();
    $user->starredSkills()->attach($skill->id, ['starred_at' => now()]);
    $this->actingAs($user);

    $this->get(route('enki'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Enki')
            ->where('skills.data.0.starred', true)
        );
});

test('enki props include categories, tints, filters and skill counts', function () {
    $this->seed(CategorySeeder::class);

    $this->actingAs(User::factory()->create());

    $this->get(route('enki'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Enki')
            ->has('categories', 9)
            ->has('tints', 6)
            ->has('filters')
            ->has('skillCounts')
        );
});

test('server-side search filters skills by name', function () {
    $author = Author::factory()->create();
    Skill::factory()->for($author)->create(['slug' => 'a/match', 'name' => 'Matching Skill']);
    Skill::factory()->for($author)->create(['slug' => 'b/other', 'name' => 'Other Skill']);

    $this->actingAs(User::factory()->create());

    $this->get(route('enki', ['q' => 'matching']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('skills.total', 1)
            ->where('skills.data.0.slug', 'a/match')
        );
});

test('server-side category filter narrows results', function () {
    $this->seed(CategorySeeder::class);
    $author = Author::factory()->create();
    $coding = Category::where('slug', 'coding')->first();
    $research = Category::where('slug', 'research')->first();

    $codingSkill = Skill::factory()->for($author)->create(['slug' => 'coding/one']);
    $codingSkill->categories()->attach($coding);

    $researchSkill = Skill::factory()->for($author)->create(['slug' => 'research/one']);
    $researchSkill->categories()->attach($research);

    $this->actingAs(User::factory()->create());

    $this->get(route('enki', ['category' => 'coding']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('skills.total', 1)
            ->where('skills.data.0.slug', 'coding/one')
        );
});

test('enki.skill route loads the named skill as selectedSkill', function () {
    $author = Author::factory()->create();
    $skill = Skill::factory()->for($author)->create(['slug' => 'coding/my-skill']);

    $this->actingAs(User::factory()->create());

    $this->get(route('enki.skill', ['slug' => 'coding/my-skill']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Enki')
            ->where('selectedSkill.slug', 'coding/my-skill')
            ->has('selectedSkill.readmeHtml')
        );
});

test('enki.skill route returns 404 for unknown slug', function () {
    $this->actingAs(User::factory()->create());
    $this->get(route('enki.skill', ['slug' => 'missing/skill']))->assertNotFound();
});

test('skill download streams a zip of the skill files', function () {
    Storage::fake('skill_data');

    $author = Author::factory()->create();
    $skill = Skill::factory()->for($author)->create(['slug' => 'coding/zip-me']);

    $content = $skill->getContent();
    $content->put('SKILL.md', "---\nname: zip-me\n---\nHello");
    $content->put('docs/notes.md', '# notes');

    $this->actingAs(User::factory()->create());

    $response = $this->get(route('enki.skill.download', ['slug' => 'coding/zip-me']));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/zip');
    expect($response->headers->get('content-disposition'))->toContain('coding-zip-me.zip');

    $tmp = tempnam(sys_get_temp_dir(), 'zipdl-');
    file_put_contents($tmp, $response->streamedContent() ?: $response->getContent());

    $zip = new \ZipArchive;
    expect($zip->open($tmp))->toBeTrue();
    expect($zip->getFromName('SKILL.md'))->toBe("---\nname: zip-me\n---\nHello");
    expect($zip->getFromName('docs/notes.md'))->toBe('# notes');
    $zip->close();
    @unlink($tmp);
});

test('skill download returns 404 when the skill has no stored files', function () {
    Storage::fake('skill_data');

    $author = Author::factory()->create();
    Skill::factory()->for($author)->create(['slug' => 'coding/empty']);

    $this->actingAs(User::factory()->create());

    $this->get(route('enki.skill.download', ['slug' => 'coding/empty']))->assertNotFound();
});
