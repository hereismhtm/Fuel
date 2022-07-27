<?php

namespace App\Http;

enum Stamp: string
{
    case ServiceUnavailable = 'Service Unavailable.';
    case InternalDatabaseError = 'Internal database error';
    case UserDataDamage = 'User data stored in server are damaged';
    case FailedProcess = 'Failed process';
    case PreconditionFailed = 'Precondition Failed.';
    case Forbidden = 'Forbidden.';
    case BadRequest = 'Bad Request.';
    case CalmDown = 'You send many requests in a short time, calm down';
    case VerificationCodeSendFailed = 'Verification code send failed';
    case WrongVerificationCode = 'Wrong verification code';
    case LoginFirst = 'Login first';
    case NotDefined = 'Not defined';
    case OutOfService = 'Out of service';
    case PriceMismatch = 'Fuel price mismatch, try again';
    case NoEnoughCredit = 'No enough credit';
    case Fail = 'Fail';
    case Success = 'Success';
    case VerificationCodeBeenSent = 'Verification code already has been sent';
    case VerificationCodeSent = 'Verification code sent successfully';
    case UserEmailSaved = 'User E-mail saved';

    public function data(): array
    {
        return match ($this) {
            Stamp::ServiceUnavailable => ['code' => 503],
            Stamp::InternalDatabaseError => ['code' => 500],
            Stamp::UserDataDamage => ['code' => 500],
            Stamp::FailedProcess => ['code' => 507],
            Stamp::PreconditionFailed => ['code' => 412],
            Stamp::Forbidden => ['code' => 403],
            Stamp::BadRequest => ['code' => 400],
            Stamp::CalmDown => ['code' => 429, 'penalty' => 1],
            Stamp::VerificationCodeSendFailed => ['code' => 422],
            Stamp::WrongVerificationCode => ['code' => 422, 'penalty' => 4],
            Stamp::LoginFirst => ['code' => 401, 'penalty' => 4],
            Stamp::NotDefined => ['code' => 422],
            Stamp::OutOfService => ['code' => 422],
            Stamp::PriceMismatch => ['code' => 422],
            Stamp::NoEnoughCredit => ['code' => 422],
            Stamp::Fail => ['code' => 422],
            Stamp::Success => ['code' => 200],
            Stamp::VerificationCodeBeenSent => ['code' => 200],
            Stamp::VerificationCodeSent => ['code' => 200],
            Stamp::UserEmailSaved => ['code' => 200],
        };
    }
}
