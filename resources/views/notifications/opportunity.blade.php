@php
$bold = $isEmail ? '**' : '`';
$files = [];
@endphp
@if(filled($opportunity->title))
{{$bold}}{{ $opportunity->title }}{{$bold}}
@endif
@if(filled($opportunity->files) && $opportunity->files->isNotEmpty())

@foreach($opportunity->files as $file)
@php
$files[] = sprintf(($isEmail ? '!' : '') . '[🖼](%s)', $file)
@endphp
@endforeach
{{implode('', $files)}}
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
@if((filled($opportunity->emails) && $opportunity->emails->isNotEmpty()) || (filled($opportunity->urls) && $opportunity->urls->isNotEmpty()))

{{$bold}}Como se candidatar:{{$bold}}
{{$opportunity->emails->concat($opportunity->urls)->filter()->implode(', ')}}
@endif
@if(\Illuminate\Support\Str::contains(strtolower($opportunity->origin->implode('|')), ['clubinfobsb', 'clubedevagas']))

{{$bold}}Fonte:{{$bold}} www.clubedevagas.com.br
@endif

{{\App\Helpers\BotHelper::getGroupSign($isEmail)}}
