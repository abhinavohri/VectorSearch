const { createApp } = Vue;

createApp({
    data() {
        return {
            isOpen: false,
            messages: [],
            userInput: '',
            isTyping: false
        }
    },
    methods: {
        toggleChat() {
            this.isOpen = !this.isOpen;
            if (this.isOpen && this.messages.length === 0) {
                this.messages.push({
                    role: 'assistant',
                    text: 'Hi! How can I help you today?'
                });
            }
        },

        async sendMessage() {
            if (!this.userInput.trim()) return;

            this.messages.push({
                role: 'user',
                text: this.userInput
            });

            const message = this.userInput;
            this.userInput = '';
            this.isTyping = true;

            this.$nextTick(() => {
                this.scrollToBottom();
            });

            const formData = new FormData();
            formData.append('action', 'vs_chat');
            formData.append('nonce', vsChat.nonce);
            formData.append('message', message);

            try {
                const response = await fetch(vsChat.ajaxurl, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                this.isTyping = false;

                if (data.success) {
                    this.messages.push({
                        role: 'assistant',
                        text: data.data.message
                    });
                } else {
                    this.messages.push({
                        role: 'assistant',
                        text: 'Sorry, something went wrong. Please try again.'
                    });
                }

                this.$nextTick(() => {
                    this.scrollToBottom();
                });
            } catch (error) {
                this.isTyping = false;
                this.messages.push({
                    role: 'assistant',
                    text: 'Network error. Please check your connection.'
                });
                this.$nextTick(() => {
                    this.scrollToBottom();
                });
            }
        },

        scrollToBottom() {
            const container = this.$refs.messages;
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        }
    }
}).mount('#vs-chat-widget');
