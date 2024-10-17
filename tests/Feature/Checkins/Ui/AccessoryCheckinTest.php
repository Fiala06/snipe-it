<?php

namespace Tests\Feature\Checkins\Ui;

use App\Events\CheckoutableCheckedIn;
use App\Mail\CheckoutAccessoryMail;
use App\Models\Accessory;
use App\Models\User;
use App\Notifications\CheckinAccessoryNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccessoryCheckinTest extends TestCase
{
    public function testCheckingInAccessoryRequiresCorrectPermission()
    {
        $accessory = Accessory::factory()->checkedOutToUser()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('accessories.checkin.store', $accessory->checkouts->first()->id))
            ->assertForbidden();
    }

    public function testAccessoryCanBeCheckedIn()
    {
        Event::fake([CheckoutableCheckedIn::class]);

        $user = User::factory()->create();
        $accessory = Accessory::factory()->checkedOutToUser($user)->create();

        $this->assertTrue($accessory->checkouts()->where('assigned_type', User::class)->where('assigned_to', $user->id)->count() > 0);

        $this->actingAs(User::factory()->checkinAccessories()->create())
            ->post(route('accessories.checkin.store', $accessory->checkouts->first()->id));

        $this->assertFalse($accessory->fresh()->checkouts()->where('assigned_type', User::class)->where('assigned_to', $user->id)->count() > 0);

        Event::assertDispatched(CheckoutableCheckedIn::class, 1);
    }

    public function testEmailSentToUserIfSettingEnabled()
    {
        Mail::fake();

        $user = User::factory()->create();
        $accessory = Accessory::factory()->checkedOutToUser($user)->create();

        $accessory->category->update(['checkin_email' => true]);

        event(new CheckoutableCheckedIn(
            $accessory,
            $user,
            User::factory()->checkinAccessories()->create(),
            '',
        ));

        Mail::assertSent(CheckoutAccessoryMail::class, function ($mail) use ($accessory, $user) {
            return $mail->hasTo($user) && $mail->contains($accessory);
        });
    }

    public function testEmailNotSentToUserIfSettingDisabled()
    {
        Mail::fake();

        $user = User::factory()->create();
        $accessory = Accessory::factory()->checkedOutToUser($user)->create();

        $accessory->category->update(['checkin_email' => false]);

        event(new CheckoutableCheckedIn(
            $accessory,
            $user,
            User::factory()->checkinAccessories()->create(),
            '',
        ));

        Mail::assertNotSent(CheckoutAccessoryMail::class, function ($mail) use ($accessory, $user) {
            return $mail->hasTo($user) && $mail->contains($accessory);
        });
    }
}
