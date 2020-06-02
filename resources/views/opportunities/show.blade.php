@extends('layouts.app')

@section('content')
    @if(filled($opportunity->title))
        <h3 class="pb-3 mb-4 border-bottom mt-3">{{ $opportunity->title }}</h3>
    @endif
    @if(filled($opportunity->files) && $opportunity->files->isNotEmpty())
        <p>
        @foreach(json_decode($opportunity->files) as $file)
            <img class="img-fluid" src="{{$file}}" title="{{$opportunity->title}}" alt="{{$opportunity->title}}"/>
        @endforeach
        </p>
    @endif
    @if(filled($opportunity->description))
        <strong>Descrição:</strong>
        <div>{!! \GrahamCampbell\Markdown\Facades\Markdown::convertToHtml($opportunity->description) !!}</div>
    @endif
    @if(filled($opportunity->position))
        <p>
        <strong>Cargo:</strong>
        {{$opportunity->position}}
        </p>
    @endif
    @if(filled($opportunity->company))
        <p>
        <strong>Empresa:</strong>
        {{$opportunity->company}}
        </p>
    @endif
    @if(filled($opportunity->salary))
        <p>
        <strong>Salário:</strong>
        {{$opportunity->salary}}
        </p>
    @endif
    @if(filled($opportunity->location))
        <p>
        <strong>Localização:</strong>
        {{$opportunity->location}}
        </p>
    @endif
    @if(filled($opportunity->tags) && $opportunity->tags->isNotEmpty())
        <p>
        <strong>Tags:</strong>
        {{$opportunity->tags->implode(' ')}}
        </p>
    @endif
    @if((filled($opportunity->emails) && $opportunity->emails->isNotEmpty()) || (filled($opportunity->urls) && $opportunity->urls->isNotEmpty()))
        <p>
        <strong>Como se candidatar:</strong>
        {{$opportunity->emails->concat($opportunity->urls)->filter()->implode(', ')}}
        </p>
    @endif
@endsection
