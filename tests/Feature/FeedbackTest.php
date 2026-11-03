<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_comment_reaches_the_owner_with_the_kind_of_screen_only(): void
    {
        $owner = User::factory()->create(['name' => 'Manuel']);
        $friend = User::factory()->create(['name' => 'Ana']);

        $this->actingAs($friend)->from('/trips')->post('/comentarios', [
            'message' => "No entendí el reparto del pasaje.\nEsperaba verlo por producto.",
            'screen' => 'Trips/Show#gastos', 'viewport_width' => 390,
        ])->assertRedirect('/trips')->assertSessionHas('success', 'Gracias. Tu comentario le llegó a Manuel.');
        $this->assertDatabaseHas('feedback', ['user_id' => $friend->id, 'author_name' => 'Ana', 'screen' => 'Trips/Show#gastos', 'viewport_width' => 390]);
        $this->get('/personas')->assertForbidden();

        $this->actingAs($owner)->get('/trips')->assertInertia(fn (Assert $page) => $page->where('unreadFeedback', 1)->etc());
        $this->get('/personas')->assertInertia(fn (Assert $page) => $page
            ->has('feedback', 1)
            ->where('feedback_total', 1)
            ->where('feedback.0.author', 'Ana')
            ->where('feedback.0.screen', 'Trips/Show#gastos')
            ->where('feedback.0.new', true)
            ->etc());
        // Opening Personas marks them as read.
        $this->get('/trips')->assertInertia(fn (Assert $page) => $page->where('unreadFeedback', 0)->etc());
        $this->get('/personas')->assertInertia(fn (Assert $page) => $page->where('feedback.0.new', false)->etc());
        $this->actingAs($friend)->get('/trips')->assertInertia(fn (Assert $page) => $page->where('unreadFeedback', 0)->etc());
    }

    public function test_a_comment_needs_text_and_cannot_carry_names_or_amounts_as_the_screen(): void
    {
        User::factory()->create();
        $friend = User::factory()->create();
        $this->actingAs($friend)->post('/comentarios', ['message' => ''])->assertSessionHasErrors(['message' => 'Escribe tu comentario.']);
        $this->post('/comentarios', ['message' => str_repeat('a', 2001)])->assertSessionHasErrors('message');
        $this->post('/comentarios', ['message' => 'Hola', 'screen' => 'Viaje a Brasil $2.063.000'])->assertSessionHasErrors('screen');
        $this->assertDatabaseCount('feedback', 0);
        $this->post('/comentarios', ['message' => 'Sin pantalla'])->assertSessionHasNoErrors();
        $this->assertNull(Feedback::sole()->screen);
    }

    public function test_guests_cannot_comment(): void
    {
        $this->post('/comentarios', ['message' => 'Hola'])->assertRedirect(route('login'));
        $this->assertDatabaseCount('feedback', 0);
    }
}
