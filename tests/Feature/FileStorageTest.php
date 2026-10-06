<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Attachment;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\LearningActivity;
use App\Models\Student;
use App\Models\User;
use App\Services\AttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class FileStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_people_upload_replace_and_remove_their_profile_picture(): void
    {
        $user = User::factory()->withRole(RoleName::Student)->create();

        $this->actingAs($user)->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->image('me.png')])->assertSessionHas('success');
        $first = $user->fresh()->avatar_path;
        $this->assertStringStartsWith('avatars/', $first);
        Storage::disk('local')->assertExists($first);

        $this->actingAs($user)->get('/dashboard')->assertInertia(fn ($page) => $page->where('auth.user.avatar_url', $user->fresh()->avatarUrl()));

        $this->actingAs($user)->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->image('new.jpg')]);
        Storage::disk('local')->assertMissing($first);

        $this->actingAs($user)->delete(route('profile.avatar.destroy'));
        $this->assertNull($user->fresh()->avatar_path);
        $this->assertCount(0, Storage::disk('local')->allFiles('avatars'));
    }

    public function test_profile_pictures_are_shown_to_signed_in_people_only(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->image('me.png')]);

        $this->actingAs(User::factory()->create())->get(route('avatars.show', $user))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        auth()->logout();
        $this->get(route('avatars.show', $user))->assertRedirect(route('login'));

        $this->actingAs($user)->get(route('avatars.show', User::factory()->create()))->assertNotFound();
    }

    public function test_profile_pictures_must_be_real_images(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->create('logo.svg', 2, 'image/svg+xml')])->assertSessionHasErrors('avatar');
        $this->actingAs($user)->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->image('huge.png')->size(3000)])->assertSessionHasErrors('avatar');
        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_deleting_an_account_deletes_its_picture(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('profile.avatar.update'), ['avatar' => UploadedFile::fake()->image('me.png')]);
        $path = $user->fresh()->avatar_path;

        $user->fresh()->delete();

        Storage::disk('local')->assertMissing($path);
    }

    public function test_forum_posts_carry_files_that_follow_the_posts_visibility(): void
    {
        $topic = ForumTopic::factory()->create();
        $author = User::factory()->withRole(RoleName::Student)->create();

        $this->actingAs($author)->post(route('forum.topics.reply', $topic), [
            'body' => 'Here are my notes.',
            'attachments' => [UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $attachment = Attachment::sole();
        $this->assertStringStartsWith("forum/{$topic->id}/", $attachment->path);

        $reader = User::factory()->withRole(RoleName::Student)->create();
        $moderator = User::factory()->withRole(RoleName::Teacher)->create();

        $this->actingAs($reader)->get(route('attachments.show', $attachment))->assertOk();
        $this->actingAs($reader)
            ->get(route('forum.topics.show', $topic))
            ->assertInertia(fn ($page) => $page->where('posts.data.1.attachments.0.name', 'notes.pdf'));

        // Once the post is removed, only moderators can open its files.
        $post = ForumPost::query()->whereKeyNot($topic->firstPost->id)->sole();
        $this->actingAs($moderator)->delete(route('forum.posts.destroy', $post));

        $this->actingAs($reader)->get(route('attachments.show', $attachment))->assertForbidden();
        $this->actingAs($moderator)->get(route('attachments.show', $attachment))->assertOk();
    }

    public function test_the_name_sent_by_the_browser_is_never_used_as_a_path(): void
    {
        $activity = LearningActivity::factory()->published()->create();
        $student = Student::factory()->create();
        Enrollment::factory()->for($student)->create(['school_class_id' => $activity->classSubject->school_class_id]);

        $this->actingAs($student->user)->post(route('submissions.store', $activity), [
            'attachments' => [UploadedFile::fake()->create('../../../.env.pdf', 5, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $attachment = Attachment::sole();
        $this->assertSame('.env.pdf', $attachment->original_name);
        $this->assertStringStartsWith("submissions/{$activity->id}/", $attachment->path);
        $this->assertStringNotContainsString('..', $attachment->path);
        $this->assertStringNotContainsString('env', $attachment->path);
    }

    public function test_a_file_whose_content_does_not_match_its_extension_is_rejected(): void
    {
        // Real files, so their content is inspected (fake uploads report a type from their name).
        @mkdir(storage_path('framework/testing'), recursive: true);
        $script = tempnam(storage_path('framework/testing'), 'upload');
        // Harmless PHP: a web-shell pattern gets the file blocked by antivirus scanners.
        file_put_contents($script, "<?php echo 'hello'; ?>\n");
        $text = tempnam(storage_path('framework/testing'), 'upload');
        file_put_contents($text, "Chapter 1 notes\n");

        $passes = fn (string $path, string $name) => Validator::make(
            ['file' => new UploadedFile($path, $name, null, null, true)],
            ['file' => AttachmentService::fileRules()],
        )->passes();

        $this->assertFalse($passes($script, 'homework.pdf'), 'A script named .pdf was accepted.');
        $this->assertFalse($passes($text, 'report.pdf'), 'Plain text named .pdf was accepted.');
        $this->assertTrue($passes($text, 'notes.txt'));
        $this->assertTrue($passes($text, 'notes.csv'));

        unlink($script);
        unlink($text);
    }

    public function test_files_on_records_without_a_policy_cannot_be_downloaded(): void
    {
        $attachment = new Attachment;
        $attachment->attachable_type = ClassSubject::class.'Missing';
        $attachment->attachable_id = 1;
        $attachment->disk = 'local';
        $attachment->path = 'stray/file.pdf';
        $attachment->original_name = 'file.pdf';
        $attachment->size = 1;
        $attachment->save();

        $this->actingAs(User::factory()->withRole(RoleName::SuperAdmin)->create())
            ->get(route('attachments.show', $attachment))
            ->assertForbidden();
    }
}
