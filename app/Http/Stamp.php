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

    public function code(): int
    {
        return match ($this) {
            Stamp::ServiceUnavailable => 503,
            Stamp::InternalDatabaseError => 500,
            Stamp::UserDataDamage => 500,
            Stamp::FailedProcess => 507,
            Stamp::PreconditionFailed => 412,
            Stamp::Forbidden => 403,
            Stamp::BadRequest => 400,
            Stamp::CalmDown => 429,
            Stamp::VerificationCodeSendFailed => 422,
            Stamp::WrongVerificationCode => 422,
            Stamp::LoginFirst => 401,
            Stamp::NotDefined => 422,
            Stamp::OutOfService => 422,
            Stamp::PriceMismatch => 422,
            Stamp::NoEnoughCredit => 422,
            Stamp::Fail => 422,
            Stamp::Success => 200,
            Stamp::VerificationCodeBeenSent => 200,
            Stamp::VerificationCodeSent => 200,
            Stamp::UserEmailSaved => 200,
        };
    }
}
