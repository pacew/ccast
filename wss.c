#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <string.h>
#include <ctype.h>
#include <errno.h>
#include <fcntl.h>

#include <libwebsockets.h>

int wss_port;

void
usage (void)
{
	fprintf (stderr, "usage: wss\n");
	exit (1);
}

int
find_wss_port (void)
{
	FILE *f;
	char buf[1000];
	char *p;
	int port;

	if ((f = fopen ("TMP.conf", "r")) == NULL)
		return (-1);

	while (fgets (buf, sizeof buf, f) != NULL) {
		p = buf;
		while (*p && strncmp (p, "wss_port", 8) != 0)
			p++;

		if (*p == 0)
			continue;
		
		while (*p && ! isdigit (*p))
			p++;

		port = atoi (p);
		if (port)
			return (port);
	}

	fclose (f);
	return (-1);
}

struct work {
	struct work *next;
	struct work *prev;
	int fd;
	int events;
	void (*callback)(struct work *wp, int revents);
};

struct work work_head;
int work_count;

struct work *
add_work (int fd, int events)
{
	struct work *wp;

	if ((wp = calloc (1, sizeof *wp)) == NULL) {
		printf ("out of memory\n");
		exit (1);
	}

	wp->fd = fd;
	wp->events = events;

	wp->next = &work_head;
	wp->prev = work_head.prev;
	work_head.prev->next = wp;
	work_head.prev = wp;
	work_count++;
	
	return (wp);
}

struct work *
find_work (int fd)
{
	struct work *wp;

	for (wp = work_head.next; wp != &work_head; wp = wp->next) {
		if (wp->fd == fd) {
			return (wp);
		}
	}
	return (NULL);
}

void
delete_work (struct work *wp)
{
	wp->next->prev = wp->prev;
	wp->prev->next = wp->next;
	free (wp);
	work_count--;
}

int
callback_http (struct lws *wsi,
	       enum lws_callback_reasons reason,
	       void *user,
	       void *in, size_t len)
{
	struct work *wp;
	struct lws_pollargs *pollargs;
	char *url;

	switch (reason) {
	case LWS_CALLBACK_PROTOCOL_INIT:
		printf ("cb: protocol init (%p %p %p %ld)\n",
			wsi, user, in, len);
		break;

	case LWS_CALLBACK_HTTP:
		printf ("cb: http (%p %p %p %ld)\n",
			wsi, user, in, len);
		url = in;
		printf ("url = %s\n", url);
		return (1);

	case LWS_CALLBACK_ADD_POLL_FD:
		pollargs = in;
		wp = add_work (pollargs->fd, pollargs->events);
		break;

	case LWS_CALLBACK_DEL_POLL_FD:
		pollargs = in;
		if ((wp = find_work (pollargs->fd)) != NULL)
			delete_work (wp);
		break;

	case LWS_CALLBACK_LOCK_POLL:
	case LWS_CALLBACK_UNLOCK_POLL:
	case LWS_CALLBACK_GET_THREAD_ID:
	case LWS_CALLBACK_FILTER_NETWORK_CONNECTION:
	case LWS_CALLBACK_WSI_CREATE:
	case LWS_CALLBACK_WSI_DESTROY:
	case LWS_CALLBACK_SERVER_NEW_CLIENT_INSTANTIATED:
	case LWS_CALLBACK_CLOSED_HTTP:
	case LWS_CALLBACK_FILTER_HTTP_CONNECTION:
		return (0);

	default:
		printf ("unknown callback %d  (%p %p %p %ld)\n",
			reason, wsi, user, in, len);
		exit (1);
		break;
	}
	return (0);
}


struct lws_protocols protocols[] = {
	{ "http", callback_http },
};

struct lws_context *context;

void
socket_setup (void)
{
	struct lws_context_creation_info info;

	memset (&info, 0, sizeof info);
	info.port = wss_port;
	info.protocols = protocols;
	info.uid = -1;
	info.gid = -1;

	context = lws_create_context (&info);
}

struct pollfd *pollfds;
int pollfds_avail;

void
work_stdin (struct work *wp, int revents)
{
	char buf[1000];
	int len;

	while (1) {
		if (fgets (buf, sizeof buf, stdin) == NULL)
			break;

		len = strlen (buf);
		while (len > 0 && isspace (buf[len-1]))
			buf[--len] = 0;

		printf ("got: %s\n", buf);
	}
}

int
main (int argc, char **argv)
{
	int c;
	struct work *wp;
	struct pollfd *pf;
	int timeout_msecs;

	while ((c = getopt (argc, argv, "")) != EOF) {
		switch (c) {
		default:
			usage ();
		}
	}

	if (optind != argc)
		usage ();

	work_head.next = &work_head;
	work_head.prev = &work_head;

	wp = add_work (0, POLLIN);
	wp->callback = work_stdin;
	fcntl (0, F_SETFL, O_NONBLOCK);

	if ((wss_port = find_wss_port ()) < 0) {
		printf ("can't find wss_port\n");
		exit (1);
	}

	printf ("wss port %d\n", wss_port);

	socket_setup ();

	while (1) {
		if (work_count > pollfds_avail) {
			pollfds_avail = work_count + 100;
			pollfds = realloc (pollfds,
					   pollfds_avail * sizeof *pollfds);
		}

		pf = pollfds;
		for (wp = work_head.next; wp != &work_head; wp = wp->next) {
			pf->fd = wp->fd;
			pf->events = wp->events;
			pf->revents = 0;
			pf++;
		}

		timeout_msecs = 1000;
		if (poll (pollfds, work_count, timeout_msecs) < 0) {
			fprintf (stderr, "poll error %d: %s\n",
				 errno, strerror (errno));
			exit (1);
		}

		lws_service (context, 0);

		pf = pollfds;
		for (wp = work_head.next; wp != &work_head; wp = wp->next) {
			if (pf->events & pf->revents) {
				if (wp->callback) {
					printf ("stdin active\n");
					(*wp->callback)(wp, pf->revents);
				}
			}
		}
	}

	return (0);
}
