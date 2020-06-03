@extends('layouts.app')

@section('content')
    {!! Form::open(['route' => 'opportunity.store', 'method' => 'post', 'files' => true]) !!}

    <fieldset>

        <legend>Enviar nova vaga</legend>

        @if(Session::has('success'))
            <p class="alert {{ Session::get('alert-class', 'alert-info') }}">{{ Session::get('success') }}</p>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <!-- Title -->
        <div class="form-group">
            {!! Form::label('title', 'Título da Vaga:', ['class' => 'col-lg-4 control-label']) !!}
            <div class="col-lg-12">
                {!! Form::text('title', old('title', $title ?? null), ['class' => 'form-control' . ($errors->has('title') ? ' is-invalid' : ''), 'required' => true]) !!}
                @if($errors->has('title'))
                <span class="invalid-feedback">
                    {{$errors->first('title')}}
                </span>
                @endif
            </div>
        </div>

        <!-- position -->
        <div class="form-group">
            {!! Form::label('position', 'Cargo/Função:', ['class' => 'col-lg-4 control-label']) !!}
            <div class="col-lg-12">
                {!! Form::text('position', old('position', $position ?? null), ['class' => 'form-control' . ($errors->has('position') ? ' is-invalid' : ''), 'required' => true]) !!}
                @if($errors->has('position'))
                    <span class="invalid-feedback">
                    {{$errors->first('position')}}
                </span>
                @endif
            </div>
        </div>

        <!-- description -->
        <div class="form-group">
            {!! Form::label('description', 'Descrição', ['class' => 'col-lg-4 control-label']) !!}
            <div class="col-lg-12">
                {!! Form::textarea('description', old('description', $description ?? null), ['class' => 'form-control' . ($errors->has('description') ? ' is-invalid' : ''), 'rows' => 3, 'required' => true]) !!}
                @if($errors->has('description'))
                    <span class="invalid-feedback">
                    {{$errors->first('description')}}
                </span>
                @endif
                <span class="help-block">Uma descricao detalhada das atribuicoes, tal como qualquer outra informacao ou detalhe pertinente.</span>
            </div>
        </div>

        <!-- salary -->
        <div class="form-group">
            {!! Form::label('salary', 'Salário:', ['class' => 'col-lg-4 control-label']) !!}
            <div class="col-lg-12">
                {!! Form::text('salary', old('salary', $salary ?? null), ['class' => 'form-control' . ($errors->has('salary') ? ' is-invalid' : '')]) !!}
                @if($errors->has('salary'))
                    <span class="invalid-feedback">
                    {{$errors->first('salary')}}
                </span>
                @endif
            </div>
        </div>

        <!-- company -->
        <div class="form-group">
            {!! Form::label('company', 'Empresa:', ['class' => 'col-lg-4 control-label']) !!}
            <div class="col-lg-12">
                {!! Form::text('company', old('company', $company ?? null), ['class' => 'form-control' . ($errors->has('company') ? ' is-invalid' : '')]) !!}
                @if($errors->has('company'))
                    <span class="invalid-feedback">
                    {{$errors->first('company')}}
                </span>
                @endif
            </div>
        </div>

        <!-- location -->
        <div class="form-group">
            {!! Form::label('location', 'Localidade:', ['class' => 'col-lg-4 control-label']) !!}
            <div class="col-lg-12">
                {!! Form::text('location', old('location', $location ?? null), ['class' => 'form-control' . ($errors->has('location') ? ' is-invalid' : ''), 'required' => true]) !!}
                @if($errors->has('location'))
                    <span class="invalid-feedback">
                    {{$errors->first('location')}}
                </span>
                @endif
            </div>
        </div>

        <!-- urls -->
        <div class="form-group">
            {!! Form::label('urls', 'Links para se candidatar (se houver)', ['class' => 'col-lg-4 control-label']) !!}
            <div class="col-lg-12">
                {!! Form::textarea('urls', old('urls', $urls ?? null), ['class' => 'form-control' . ($errors->has('urls') ? ' is-invalid' : ''), 'rows' => 3]) !!}
                @if($errors->has('urls'))
                    <span class="invalid-feedback">
                    {{$errors->first('urls')}}
                </span>
                @endif
                <span class="help-block">Uma URL por linha.</span>
            </div>
        </div>

        <!-- emails -->
        <div class="form-group">
            {!! Form::label('emails', 'E-mails para contato (se houver)', ['class' => 'col-lg-4 control-label']) !!}
            <div class="col-lg-12">
                {!! Form::textarea('emails', old('emails', $emails ?? null), ['class' => 'form-control' . ($errors->has('emails') ? ' is-invalid' : ''), 'rows' => 3]) !!}
                @if($errors->has('emails'))
                    <span class="invalid-feedback">
                    {{$errors->first('emails')}}
                </span>
                @endif
                <span class="help-block">Um e-mail por linha.</span>
            </div>
        </div>

        <!-- files -->
        <div class="form-group">
            {!! Form::label('files', 'Imagem ilustrativa', ['class' => 'col-lg-4 control-label']) !!}
            <div class="col-lg-12">
                {!! Form::file('files', ['class' => 'form-control' . ($errors->has('files') ? ' is-invalid' : '')]) !!}
                @if($errors->has('files'))
                    <span class="invalid-feedback">
                    {{$errors->first('files')}}
                </span>
                @endif
                <span class="help-block">Formatos: PEG, JPG, BMP, PNG, GIF, WEBP, TIFF ou TIF.</span>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="form-group">
            <div class="col-lg-12 col-lg-offset-2">
                {!! Form::submit('Enviar', ['class' => 'btn btn-lg btn-info pull-right'] ) !!}
            </div>
        </div>

    </fieldset>
    {!! Form::close() !!}
@endsection
