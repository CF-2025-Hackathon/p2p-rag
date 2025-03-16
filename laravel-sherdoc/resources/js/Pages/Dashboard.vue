<script setup>
import { nextTick, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import VueMarkdown from 'vue-markdown-render';

Echo.channel('stream-channel')
    .listen('StreamUpdated', (e) => {
        const msg = messages.value[messages.value.length - 1].msg;
        messages.value[messages.value.length - 1].msg = msg + e.data;

        window.scrollTo(0, document.body.scrollHeight || document.documentElement.scrollHeight);
    })
    .listen('StreamDone', async (e) => {
        chatEnabled.value = true;
        await nextTick();
        chatInput.value.focus();

        window.scrollTo(0, document.body.scrollHeight || document.documentElement.scrollHeight);
    })
    .listen('CrawlUpdated', async (e) => {
        crawledUrls.value += e.data + "\n";
    })
    .listen('CrawlDone', async (e) => {
        crawlEnabled.value = true;
    })
    .listen('UrlScraped', async (e) => {
        isScraping.value = true;
        scrapeMessage.value = 'Scraped: ' + e.countScraped + ' / ' + e.countTotal;
    })
    .listen('ScrapeDone', async (e) => {
        isScraping.value = false;
    });

const chatInput = ref(null);
const chatEnabled = ref(true);
const message = ref('');
const messages = ref([]);
const sourceUrl = ref('');
const crawledUrls = ref('');
const crawlEnabled = ref(true);
const scrapeMessage = ref('');
const isScraping = ref(false);

const sendMessage = () => {
    chatEnabled.value = false;

    messages.value.push({
        'role': 'q',
        'msg': message.value,
    });

    message.value = '';
    axios.post('/api/chat', { messages: messages.value })
        .then((response) => {
            console.log(response.data);

            window.scrollTo(0, document.body.scrollHeight || document.documentElement.scrollHeight);
        });

    messages.value.push({
        'role': 'a',
        'msg': '',
    });

    window.scrollTo(0, document.body.scrollHeight || document.documentElement.scrollHeight);
};

const crawl = () => {
    crawlEnabled.value = false;
    crawledUrls.value = '';
    axios.post('/api/crawl', { url: sourceUrl.value })
        .then((response) => {
            console.log(response.data);
        });
};

const scrape = () => {
    axios.post('/api/scrape', { urls: crawledUrls.value.split("\n").filter(Boolean) })
        .then((response) => {
            console.log(response.data);
        });

    crawledUrls.value = '';
};
</script>

<template>
    <AppLayout title="Dashboard">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    P2P RAG Chatbot
                </h2>

                <div>
                    <input ref="chatInput" v-model="sourceUrl" type="text" class="border border-slate-300 p-2 rounded">
                    <button class="border border-slate-300 p-2 ml-2 rounded shadow-md" @click="crawl">Crawl</button>
                    <button class="border border-slate-300 p-2 ml-2 rounded shadow-md" @click="scrape">Scrape</button>
                </div>
            </div>
        </template>

        <div class="py-12 pb-20">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 flex flex-col">
                <div>
                    <div v-if="isScraping" class="mt-4 text-xl">
                        <span>{{ scrapeMessage }}</span>
                    </div>

                    <div>
                        <textarea v-if="crawledUrls !== ''" v-model="crawledUrls" :disabled="!crawlEnabled" class="w-full h-96 p-2 rounded mt-4 disabled:opacity-50"></textarea>
                    </div>

                    <div v-for="message in messages" :key="message" class="dark:bg-gray-800 overflow-hidden shadow-md sm:rounded-lg mb-4" :class="message.role === 'q' ? 'bg-slate-50 mr-8' : 'bg-white ml-8'">
                        <div class="p-4">
                            <span class="font-bold text-gray-300">{{ message.role === 'q' ? 'Question: ' : 'Answer: ' }}</span>
                            <div><vue-markdown :source="message.msg" /></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="fixed bottom-0 w-full p-4 bg-white shadow-xl border-t-2 border-slate-300">
            <input ref="chatInput" :disabled="!chatEnabled" v-model="message" @keyup.enter="sendMessage" type="text" class="w-full border border-slate-300 p-2 rounded">
        </div>
    </AppLayout>
</template>
