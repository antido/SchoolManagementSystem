<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRole(RoleName::Admin)->create();
    }

    public function test_only_published_questions_are_shown_grouped_by_category(): void
    {
        $grades = FaqCategory::create(['name' => 'Grades', 'order' => 1]);
        Faq::factory()->create(['faq_category_id' => $grades->id, 'question' => 'How are grades computed?']);
        Faq::factory()->unpublished()->create(['faq_category_id' => $grades->id, 'question' => 'Draft question']);
        Faq::factory()->create(['question' => 'How do I reset my password?']);

        $this->get(route('faqs.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Faq/Index')
                ->has('groups', 2)
                ->where('groups.0.name', 'Grades')
                ->has('groups.0.faqs', 1)
                ->where('groups.0.faqs.0.question', 'How are grades computed?')
                ->where('groups.1.name', 'Other questions')
                ->where('can.manage', false)
            );
    }

    public function test_questions_and_answers_can_be_searched(): void
    {
        Faq::factory()->create(['question' => 'Where do I see my attendance?', 'answer' => 'Open Attendance in the menu.']);
        Faq::factory()->create(['question' => 'Can I change my email?', 'answer' => 'Ask the registrar.']);
        Faq::factory()->unpublished()->create(['question' => 'Attendance secret', 'answer' => 'Hidden']);

        $this->get(route('faqs.index', ['search' => 'attendance']))
            ->assertInertia(fn ($page) => $page->has('groups.0.faqs', 1)->where('search', 'attendance'));

        $this->get(route('faqs.index', ['search' => 'registrar']))
            ->assertInertia(fn ($page) => $page->where('groups.0.faqs.0.question', 'Can I change my email?'));
    }

    public function test_the_faq_can_be_limited_to_signed_in_users(): void
    {
        config(['school.faq_public' => false]);

        $this->get(route('faqs.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->withRole(RoleName::Student)->create())->get(route('faqs.index'))->assertOk();
        // An account without the faqs.view permission.
        $this->actingAs(User::factory()->create())->get(route('faqs.index'))->assertForbidden();
    }

    public function test_admins_add_edit_publish_and_delete_questions(): void
    {
        $admin = $this->admin();
        $category = FaqCategory::create(['name' => 'Accounts', 'order' => 0]);

        $this->actingAs($admin)->post(route('faqs.store'), [
            'faq_category_id' => $category->id,
            'question' => 'How do I log in?',
            'answer' => 'Use your school email.',
            'is_published' => false,
        ])->assertRedirect(route('faqs.manage'));

        $faq = Faq::sole();
        $this->assertFalse($faq->is_published);
        $this->assertSame(1, $faq->order);

        $this->actingAs($admin)->patch(route('faqs.publish', $faq));
        $this->assertTrue($faq->fresh()->is_published);

        $this->actingAs($admin)->put(route('faqs.update', $faq), [
            'faq_category_id' => null,
            'question' => 'How do I sign in?',
            'answer' => 'Use your school email.',
            'is_published' => true,
        ]);
        $this->assertSame('How do I sign in?', $faq->fresh()->question);
        $this->assertNull($faq->fresh()->faq_category_id);

        $this->actingAs($admin)->delete(route('faqs.destroy', $faq));
        $this->assertModelMissing($faq);
    }

    public function test_questions_are_reordered_within_their_category(): void
    {
        $admin = $this->admin();
        [$a, $b, $c] = Faq::factory()->count(3)->create()->all();

        $this->actingAs($admin)->patch(route('faqs.move', $c), ['direction' => 'up']);

        $this->assertSame([$a->id, $c->id, $b->id], Faq::query()->orderBy('order')->pluck('id')->all());

        $this->get(route('faqs.index'))->assertInertia(fn ($page) => $page->where('groups.0.faqs.1.id', $c->id));
    }

    public function test_deleting_a_category_keeps_its_questions(): void
    {
        $admin = $this->admin();
        $category = FaqCategory::create(['name' => 'Old', 'order' => 0]);
        $faq = Faq::factory()->create(['faq_category_id' => $category->id]);

        $this->actingAs($admin)->delete(route('faqs.categories.destroy', $category))->assertSessionHas('success');

        $this->assertModelMissing($category);
        $this->assertNull($faq->fresh()->faq_category_id);
    }

    public function test_only_people_who_manage_the_faq_can_change_it(): void
    {
        $faq = Faq::factory()->create();

        foreach ([RoleName::Teacher, RoleName::Student, RoleName::Hr] as $role) {
            $user = User::factory()->withRole($role)->create();

            $this->actingAs($user)->get(route('faqs.manage'))->assertForbidden();
            $this->actingAs($user)->patch(route('faqs.publish', $faq))->assertForbidden();
            $this->actingAs($user)->post(route('faqs.categories.store'), ['name' => 'X'])->assertForbidden();
        }

        $this->actingAs($this->admin())->get(route('faqs.manage'))->assertInertia(fn ($page) => $page->component('Faq/Manage')->has('faqs', 1));
    }
}
