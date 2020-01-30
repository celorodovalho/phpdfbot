@php
$bold = $isEmail ? '**' : '*';
@endphp
@if(filled($opportunity->title))
{{$bold}}{{ $opportunity->title }}{{$bold}}
@endif
@if(filled($opportunity->files) && $opportunity->files->isNotEmpty())

@foreach($opportunity->files as $file)
{{sprintf(($isEmail ? '!' : '') . '[Image](%s)', $file)}}
@endforeach
@endif
@if(filled($opportunity->description))

{{$bold}}Descrição:{{$bold}}

{{$opportunity->description}}
@endif
@if(filled($opportunity->position))

{{$bold}}Cargo:{{$bold}}

{{$opportunity->position}}
@endif
@if(filled($opportunity->company))

{{$bold}}Empresa:{{$bold}}

{{$opportunity->company}}
@endif
@if(filled($opportunity->salary))

{{$bold}}Salário:{{$bold}}

{{$opportunity->salary}}
@endif
@if(filled($opportunity->location))

{{$bold}}Localização:{{$bold}}

{{$opportunity->location}}
@endif
@if(filled($opportunity->tags) && $opportunity->tags->isNotEmpty())

{{$bold}}Tags:{{$bold}}

{{$opportunity->tags->implode(' ')}}
@endif
@if(filled($opportunity->emails) || filled($opportunity->url))

{{$bold}}Como se candidatar:{{$bold}}

{{implode(', ', array_filter([$opportunity->emails, $opportunity->url]))}}
@endif
@if(\Illuminate\Support\Str::contains(strtolower($opportunity->origin), ['clubinfobsb', 'clubedevagas']))

{{$bold}}Fonte:{{$bold}} www.clubedevagas.com.br
@endif

{{\App\Helpers\BotHelper::getGroupSign($isEmail)}}
@if($hasAuthor)
@php
$userNames = explode('|', $opportunity->origin);
$userName = end($userNames);
@endphp
@if(!blank($userName))

@if(\Illuminate\Support\Str::contains($userName, ' '))
{{"by [$userName](tg://user?id={$opportunity->telegram_user_id})"}}
@else
{{'by @' . $userName}}
@endif
@endif
@endif
