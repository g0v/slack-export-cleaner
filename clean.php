<?php

foreach (glob("input/*") as $f) {
    if (is_dir($f)) {
        $channel = basename($f);
        if (!file_exists("output/{$channel}")) {
            mkdir("output/" . $channel);
        }
        foreach (glob("{$f}/*") as $channel_file) {
            $dfile = basename($channel_file);
            $content = file_get_contents($channel_file);
            $content = preg_replace_callback('#(\\\\?)([0-9a-zA-Z-_\.]+@[0-9a-zA-Z-_.]+)#', function($s) use ($channel_file) {
                if ($s[1] == '\\') {
                    $s[1] = '\\' . substr($s[2], 0, 1);
                    $s[2] = substr($s[2], 1);
                }
                list($name, $domain) = explode('@', $s[2]);
                if (strpos($domain, '.') === false) {
                    //error_log("{$channel_file} {$s[1]}{$s[2]}");
                    return $s[1] . $s[2];
                } else if (preg_match('#\.(png|jpg|jpeg)$#', $domain)) {
                    return $s[1] . $s[2];
                }
                $to = $s[2];
                $to = preg_replace('#[a-zA-Z0-9-_]#', '_', $s[2]);
                $to = $s[1] . $to;
                error_log("[$channel_file] {$s[1]}{$s[2]} => {$to}");
                return $to;
            }, $content);

            $content = preg_replace_callback('#[^0-9]09\d\d-?\d\d\d-?\d\d\d#', function($s) use ($channel_file) {
                $to = preg_replace('#\d#', '*', $s[0]);
                error_log("[{$channel_file}] {$s[0]} => $to");
                return $to;
            }, $content);

            if (!json_decode($content)) {
                throw new Exception("$channel_file failed");
            }
            file_put_contents("output/{$channel}/{$dfile}", $content);
        }
    } else {
        if (in_array($f, ['input/channels.json', 'input/integration_logs.json'])) {
            copy($f, 'output/' . basename($f));
            continue;
        } else if ($f == 'input/users.json') {
            $obj = json_decode(file_get_contents($f));
            $obj = array_map(function($o) {
                $profile = new StdClass;
                $cols = ['title', 'real_name', 'display_name', 'status_text', 'status_emoji'];
                foreach ($cols as $c) {
                    if (property_exists($o->profile, $c)) {
                        $profile->{$c} = $o->profile->{$c};
                    }
                }
                $o->profile = $profile;
                foreach ($o as $k => $v) {
                    if (!in_array($k, ['id', 'team_id', 'name', 'profile'])) {
                        unset($o->{$k});
                    }
                }
                return $o;
            }, $obj);
            file_put_contents('output/users.json', json_encode($obj, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            continue;
        }
        echo $f . "\n";
        exit;
    }
}
