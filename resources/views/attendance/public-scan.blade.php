<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Check-in | {{ $event->title }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-slide { animation: slideIn 0.3s ease-out forwards; }
    </style>
</head>
<body class="bg-[#F8FAFC] flex items-center justify-center min-h-screen p-4">

    <div class="bg-white p-8 rounded-[2.5rem] shadow-[0_20px_50px_rgba(0,0,0,0.05)] w-full max-w-md border border-gray-100">
        
        {{-- Header Section --}}
        <div class="text-center mb-10">
            <div class="relative inline-block mb-6">
                <div class="p-4 bg-blue-600 rounded-[2rem] shadow-xl shadow-blue-100 rotate-3">
                    <svg class="w-8 h-8 text-white -rotate-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                    </svg>
                </div>
                {{-- Floating Day Badge --}}
                <span class="absolute -right-6 -top-2 bg-black text-white px-3 py-1 rounded-xl text-[9px] font-black uppercase tracking-widest shadow-lg">
                    Day {{ request('day', 1) }}
                </span>
            </div>
            
            <h1 class="text-2xl font-black text-gray-900 uppercase tracking-tighter leading-none">
                {{ $event->title }}
            </h1>
            <p class="text-blue-500 font-black mt-2 text-[10px] uppercase tracking-[0.2em]">Digital Attendance Portal</p>
        </div>

        {{-- Status Messages --}}
        @if(session('success'))
            <div class="mb-8 p-4 bg-emerald-50 border border-emerald-100 rounded-3xl text-center animate-slide">
                <p class="text-emerald-700 font-black text-xs uppercase tracking-widest">
                    <span class="mr-1">✓</span> {{ session('success') }}
                </p>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-8 p-4 bg-rose-50 border border-rose-100 rounded-3xl text-center animate-slide">
                <p class="text-rose-700 font-black text-xs uppercase tracking-widest">
                    <span class="mr-1">✕</span> {{ session('error') }}
                </p>
            </div>
        @endif

        {{-- Main Form --}}
        <form action="{{ route('attendance.check', $event->id) }}" method="POST" class="space-y-8">
            @csrf
            
            {{-- CRITICAL: This hidden input carries the day from the URL into the POST request --}}
            <input type="hidden" name="day" value="{{ request('day', 1) }}">

            <div class="relative group">
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-[0.3em] mb-4 text-center">
                    Identity Verification
                </label>
                
                <div class="relative">
                    <input type="tel" 
                           name="phone" 
                           placeholder="000 000 0000" 
                           required
                           autofocus
                           inputmode="numeric"
                           class="w-full px-6 py-5 bg-gray-50 border-2 border-transparent focus:border-blue-600 focus:bg-white rounded-[2rem] text-center text-3xl font-black tracking-[0.1em] text-gray-900 outline-none transition-all duration-300 shadow-inner group-hover:bg-gray-100/50">
                </div>
                
                <p class="text-center text-[9px] text-gray-400 font-bold uppercase mt-4 tracking-widest">
                    Enter your registered phone number
                </p>
            </div>

            <button type="submit" 
                    class="group w-full bg-gray-900 hover:bg-black active:scale-95 text-white py-5 rounded-[2rem] font-black uppercase text-xs tracking-[0.3em] shadow-2xl shadow-gray-200 transition-all duration-200">
                <span class="flex items-center justify-center gap-2">
                    Check In Now
                    <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                </span>
            </button>
        </form>

        {{-- Branding Footer --}}
        <div class="mt-12 text-center">
            <div class="flex items-center justify-center gap-3 opacity-30">
                <div class="h-[1px] w-8 bg-gray-400"></div>
                <p class="text-[9px] text-gray-500 font-black uppercase tracking-[0.4em]">
                    Delisa Brand
                </p>
                <div class="h-[1px] w-8 bg-gray-400"></div>
            </div>
        </div>
    </div>

</body>
</html>