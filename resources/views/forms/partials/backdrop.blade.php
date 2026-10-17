{{--
    Page background for a public form and its thank-you page: a soft, blurred copy of the
    form's first flyer, so the page takes on the event's own colours; soft colour glows
    when the form has no flyer. Sits behind the content of a "relative isolate" section.
--}}
@php($backdropFlyer = $form->flyers()[0] ?? null)
<div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
    @if($backdropFlyer)
        <img src="{{ Storage::url($backdropFlyer) }}" alt="" class="absolute inset-0 h-full w-full scale-125 object-cover opacity-90 blur-3xl saturate-150">
        <div class="absolute inset-0 bg-gradient-to-br from-white/65 via-white/35 to-white/60"></div>
    @else
        <div class="absolute inset-0 bg-gradient-to-br from-blue-50 via-white to-violet-50"></div>
        <div class="absolute -left-32 -top-24 h-96 w-96 rounded-full bg-blue-400/25 blur-3xl"></div>
        <div class="absolute -right-24 top-1/3 h-[28rem] w-[28rem] rounded-full bg-violet-400/20 blur-3xl"></div>
        <div class="absolute -bottom-24 left-1/4 h-80 w-80 rounded-full bg-sky-300/25 blur-3xl"></div>
    @endif
    <div class="absolute inset-0 bg-[radial-gradient(rgb(15_23_42_/_0.07)_1px,transparent_1px)] [background-size:22px_22px]"></div>
</div>
