<?php

class CustomLogger extends \Psr\Log\AbstractLogger
{

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        global $mysqli;

        $pattern = '/
    \{             # Match the opening brace of the JSON object
    (              # Start capture group for the JSON content
        [^{}]* # Match any character that is NOT a brace
        |          # OR
        (?R)       # Recursively match the entire pattern (nested JSON)
    )* # Repeat the content or recursion group 0 or more times
    \}             # Match the closing brace
/x'; // 'x' modifier allows for comments and whitespace in the pattern
        if (preg_match($pattern, $message, $matches)) {
            $update = json_decode(stripslashes($matches[0]),true);
            if (isset($update['message']['message_id'])) {
                $stmt = $mysqli->prepare("INSERT IGNORE INTO messages (user_id, message_id, data) VALUES (?,?,?)") or error_log($mysqli->error);
                $ctx = json_encode($update);
                $stmt->bind_param("iis", $update['message']['chat']['id'], $update['message']['message_id'], $ctx);
                $stmt->execute();
            }
        } else {
            if (isset( $context['type'])) {
                switch ($context['type']) {
                    case "response":
                    case "⬇️ Nutgram Response":
                        if (isset($context['response']['result']['message_id'])) {
                            $stmt = $mysqli->prepare("INSERT IGNORE INTO messages (user_id, message_id, data) VALUES (?,?,?)") or error_log($mysqli->error);
                            $ctx = json_encode($context);
                            $stmt->bind_param("iis", $context['response']['result']['chat']['id'], $context['response']['result']['message_id'], $ctx);
                            $stmt->execute();
                        }
                        break;
                }
            }
        }

    }


}