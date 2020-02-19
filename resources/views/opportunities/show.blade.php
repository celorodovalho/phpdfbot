<h3>title:</h3>
<pre><code>{{$opportunity->title}}</code></pre>

<h3>position:</h3>
<pre><code>{{$opportunity->position}}</code></pre>

<h3>description:</h3>
<pre><code>{{$opportunity->description}}</code></pre>

<h3>original:</h3>
<pre><code>{{$opportunity->original}}</code></pre>

<h3>salary:</h3>
<pre><code>{{$opportunity->salary}}</code></pre>

<h3>company:</h3>
<pre><code>{{$opportunity->company}}</code></pre>

<h3>location:</h3>
<pre><code>{{$opportunity->location}}</code></pre>

<h3>files:</h3>
<pre>
<code>
@if(!empty($opportunity->files))
{{$opportunity->files->implode(', ')}}
@endif
</code>
</pre>

<h3>telegram_id:</h3>
<pre><code>{{$opportunity->telegram_id}}</code></pre>

<h3>status:</h3>
<pre><code>{{$opportunity->status}}</code></pre>

<h3>telegram_user_id:</h3>
<pre><code>{{$opportunity->telegram_user_id}}</code></pre>

<h3>url:</h3>
<pre><code>{{$opportunity->url}}</code></pre>

<h3>origin:</h3>
<pre><code>{{$opportunity->origin}}</code></pre>

<h3>tags:</h3>
<pre>
<code>
@if(!empty($opportunity->tags))
{{$opportunity->tags->implode(', ')}}
@endif
</code>
</pre>

<h3>emails:</h3>
<pre><code>{{$opportunity->emails}}</code></pre>

@dump($opportunity->toArray())