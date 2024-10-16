<?php
namespace App\Repositories;

use App\Models\BonusLogs;
use App\Models\HitAndRun;
use App\Models\Medal;
use App\Models\Message;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserMedal;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Nexus\Database\NexusDB;

class BonusRepository extends BaseRepository
{
    public function consumeToCancelHitAndRun($uid, $hitAndRunId): bool
    {
        if (!HitAndRun::getIsEnabled()) {
            throw new \LogicException("H&R not enabled.");
        }
        $user = User::query()->findOrFail($uid);
        $hitAndRun = HitAndRun::query()->findOrFail($hitAndRunId);
        if ($hitAndRun->uid != $uid) {
            throw new \LogicException("H&R: $hitAndRunId not belongs to user: $uid.");
        }
        if ($hitAndRun->status == HitAndRun::STATUS_PARDONED) {
            throw new \LogicException("H&R: $hitAndRunId already pardoned.");
        }
        $requireBonus = BonusLogs::getBonusForCancelHitAndRun();
        NexusDB::transaction(function () use ($user, $hitAndRun, $requireBonus) {
            $comment = nexus_trans('hr.bonus_cancel_comment', [
                'bonus' => $requireBonus,
            ], $user->locale);
            do_log("comment: $comment");

            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_CANCEL_HIT_AND_RUN, "$comment(H&R ID: {$hitAndRun->id})");

            $hitAndRun->update([
                'status' => HitAndRun::STATUS_PARDONED,
                'comment' => NexusDB::raw("if(comment = '', '$comment', concat_ws('\n', '$comment', comment))"),
            ]);
        });

        return true;

    }


    public function consumeToBuyMedal($uid, $medalId): bool
    {
        $user = User::query()->findOrFail($uid);
        $medal = Medal::query()->findOrFail($medalId);
        $exists = $user->valid_medals()->where('medal_id', $medalId)->exists();
        do_log(last_query());
        if ($exists) {
            throw new \LogicException("user: $uid already own this medal: $medalId.");
        }
        $requireBonus = $medal->price;
        NexusDB::transaction(function () use ($user, $medal, $requireBonus) {
            $comment = nexus_trans('bonus.comment_buy_medal', [
                'bonus' => $requireBonus,
                'medal_name' => $medal->name,
            ], $user->locale);
            do_log("comment: $comment");
            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_BUY_MEDAL, "$comment(medal ID: {$medal->id})");
            $expireAt = null;
            if ($medal->duration > 0) {
                $expireAt = Carbon::now()->addDays($medal->duration)->toDateTimeString();
            }
            $user->medals()->attach([$medal->id => ['expire_at' => $expireAt, 'status' => UserMedal::STATUS_NOT_WEARING]]);

        });

        return true;

    }

    public function consumeToGiftMedal($uid, $medalId, $toUid): bool
    {
        $user = User::query()->findOrFail($uid);
        $toUser = User::query()->findOrFail($toUid);
        $medal = Medal::query()->findOrFail($medalId);
        $exists = $toUser->valid_medals()->where('medal_id', $medalId)->exists();
        do_log(last_query());
        if ($exists) {
            throw new \LogicException("user: $toUid already own this medal: $medalId.");
        }
        $medal->checkCanBeBuy();
        $bonusTaxpercentage = \App\Models\Setting::query()->where('id', 198)->value('value');
        $requireBonus = ceil($medal->price/(1-$bonusTaxpercentage/100));
        $giftFee = ceil( $requireBonus - $medal->price);
        NexusDB::transaction(function () use ($user, $toUser, $medal, $requireBonus, $giftFee) {
            $comment = nexus_trans('bonus.comment_gift_medal', [
                'bonus' => $requireBonus,
                'medal_name' => $medal->name,
                'to_username' => $toUser->username,
            ], $user->locale);
            do_log("comment: $comment");
            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_GIFT_MEDAL, "$comment(medal ID: {$medal->id})");

            $expireAt = null;
            if ($medal->duration > 0) {
                $expireAt = Carbon::now()->addDays($medal->duration)->toDateTimeString();
            }else{
                $expireAt = '永久';
            }
            $msg = [
                'sender' => 0,
                'receiver' => $toUser->id,
                'subject' => nexus_trans('message.receive_medal.subject', [], $toUser->locale),
                'msg' => nexus_trans('message.receive_medal.body', [
                    'username' => $user->username,
                    'cost_bonus' => $requireBonus,
                    'medal_name' => $medal->name,
                    'price' => $medal->price,
                    'gift_fee_total' => $giftFee,
                    'expire_at' => $expireAt ?? nexus_trans('label.permanent'),
                    'bonus' => $medal->bonus ?? 0,
                ], $toUser->locale),
                'added' => now()
            ];
            Message::add($msg);
            $toUser->medals()->attach([$medal->id => ['expire_at' => $expireAt, 'status' => UserMedal::STATUS_NOT_WEARING]]);
            if ($medal->stock !== null) {
                $affectedRows = NexusDB::table('medals')
                    ->where('id', $medal->id)
                    ->where('stock', $medal->stock)
                    ->decrement('stock')
                ;
                if ($affectedRows != 1) {
                    throw new \RuntimeException("Decrement medal({$medal->id}) stock affected rows != 1($affectedRows)");
                }
            }

        });

        return true;

    }

    public function consumeToBuyAttendanceCard($uid): bool
    {
        $user = User::query()->findOrFail($uid);
        $requireBonus = BonusLogs::getBonusForBuyAttendanceCard();
        NexusDB::transaction(function () use ($user, $requireBonus) {
            $comment = nexus_trans('bonus.comment_buy_attendance_card', [
                'bonus' => $requireBonus,
            ], $user->locale);
            do_log("comment: $comment");
            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_BUY_ATTENDANCE_CARD, $comment);
            User::query()->where('id', $user->id)->increment('attendance_card');
        });

        return true;

    }

    public function consumeUserBonus($user, $requireBonus, $logBusinessType, $logComment = '', array $userUpdates = [])
    {
        if (!isset(BonusLogs::$businessTypes[$logBusinessType])) {
            throw new \InvalidArgumentException("Invalid logBusinessType: $logBusinessType");
        }
        if (isset($userUpdates['seedbonus']) || isset($userUpdates['bonuscomment'])) {
            throw new \InvalidArgumentException("Not support update seedbonus or bonuscomment");
        }
        if ($requireBonus <= 0) {
            return;
        }
        $user = $this->getUser($user);
        if ($user->seedbonus < $requireBonus) {
            do_log("user: {$user->id}, bonus: {$user->seedbonus} < requireBonus: $requireBonus", 'error');
            throw new \LogicException("User bonus not enough.");
        }
        NexusDB::transaction(function () use ($user, $requireBonus, $logBusinessType, $logComment, $userUpdates) {
            $logComment = addslashes($logComment);
            $bonusComment = date('Y-m-d') . " - $logComment";
            $oldUserBonus = $user->seedbonus;
            $newUserBonus = bcsub($oldUserBonus, $requireBonus);
            $log = "user: {$user->id}, requireBonus: $requireBonus, oldUserBonus: $oldUserBonus, newUserBonus: $newUserBonus, logBusinessType: $logBusinessType, logComment: $logComment";
            do_log($log);
            $userUpdates['seedbonus'] = $newUserBonus;
            $userUpdates['bonuscomment'] = NexusDB::raw("if(bonuscomment = '', '$bonusComment', concat_ws('\n', '$bonusComment', bonuscomment))");
            $affectedRows = NexusDB::table($user->getTable())
                ->where('id', $user->id)
                ->where('seedbonus', $oldUserBonus)
                ->update($userUpdates);
            if ($affectedRows != 1) {
                do_log("update user seedbonus affected rows != 1, query: " . last_query(), 'error');
                throw new \RuntimeException("Update user seedbonus fail.");
            }
            $bonusLog = [
                'business_type' => $logBusinessType,
                'uid' => $user->id,
                'old_total_value' => $oldUserBonus,
                'value' => $requireBonus,
                'new_total_value' => $newUserBonus,
                'comment' => sprintf('[%s] %s', BonusLogs::$businessTypes[$logBusinessType]['text'], $logComment),
            ];
            BonusLogs::query()->insert($bonusLog);
            do_log("bonusLog: " . nexus_json_encode($bonusLog));
        });
    }


}
